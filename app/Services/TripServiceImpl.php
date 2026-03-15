<?php

namespace App\Services;

use App\Enums\TrackingStatus;
use App\Models\Delivery;
use App\Models\DeliveryPackage;
use App\Models\Package;
use App\Services\Contracts\TripService;
use App\Services\TripService as ServicesTripService;
use Helper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TripServiceImpl implements ServicesTripService
{
    /**
     * Update trip status and counts based on current package states
     * Optimized: Use incremental updates by default, full recalculation only when needed
     */
    public function updateTripStatus(int $tripId, object $user, bool $forceRecalculate = false): ?Delivery
    {
        $trip = Delivery::where('is_deleted', 0)
            ->where('company_id', $user->company_id)
            ->find($tripId);

        if (!$trip) {
            return null;
        }

        if ($forceRecalculate) {
            // Full recalculation (slower but accurate for corrections)
            $counts = $this->recalculateTripCounts($tripId, $user);
            $this->updateTripWithCounts($trip, $counts, $user);
        } else {
            // Incremental update (faster, relies on current counts)
            $this->checkAndUpdateTripCompletion($trip, $user);
        }

        return $trip->fresh();
    }

    /**
     * Find or create an active trip for a driver
     */
    public function findOrCreateActiveTrip(
        int $driverId, 
        int $companyId, 
        int $branchId, 
        string $vehicleType, 
        object $user
    ): Delivery
    {
        // Try to find active trip
        $activeTrip = $this->getActiveTrip($driverId, $companyId);

        if ($activeTrip) {
            return $activeTrip;
        }

        // Check if last trip has pending packages
        $tripWithPending = $this->findTripWithPendingPackages($driverId, $companyId);
        
        if ($tripWithPending) {
            return $tripWithPending;
        }

        // Create new trip
        return $this->createNewTrip($driverId, $companyId, $branchId, $vehicleType, $user);
    }

    /**
     * Add package to trip
     */
    public function addPackageToTrip(
        int $tripId, 
        Package $package, 
        object $user, 
        ?string $notes = null,
        ?string $action = null
    ): bool
    {
        $trip = Delivery::find($tripId);
        
        if (!$trip) {
            return false;
        }

        // Check if package already exists in this trip
        $existing = DeliveryPackage::where('delivery_id', $tripId)
            ->where('package_id', $package->id)
            ->where('delay_count', 0)
            ->where('has_swap', 0)
            ->where('is_deleted', 0)
            ->first();

        if ($existing) {
            // Package already in trip, just update if needed
            return true;
        }

        // Mark previous attempts as delayed
        DeliveryPackage::where('package_id', $package->id)
            ->where('delay_count', 0)
            ->where('is_deleted', 0)
            ->update(['delay_count' => 1]);

        // Create new delivery package
        $deliveryPackage = DeliveryPackage::create([
            'order_id' => $package->order_id,
            'delivery_id' => $tripId,
            'payer' => $package->payer,
            'receiver_phone' => $package->receiver_phone,
            'receiver_address' => $package->receiver_address,
            'zone_code' => $package->zone_code,
            'zone_name' => $package->zone_name,
            'merchant_id' => $package->merchant_id,
            'delivery_type' => $package->delivery_type,
            'product_type' => $package->product_type,
            'notes' => $notes,
            'assign_uid' => $action == 'assign' ? $user->id : null,
            'driver_id' => $trip->driver_id,
            'package_id' => $package->id,
            'status_id' => 6, // On Delivery
            'update_uid' => $user->id,
            'create_uid' => $user->id,
            'branch_id' => $user->branch_id,
            'company_id' => $user->company_id,
        ]);

        if (!$deliveryPackage) {
            return false;
        }

        // Increment package count
        $trip->increment('package_count');

        return true;
    }

    /**
     * Add multiple packages to trip in batch (optimized for performance)
     */
    public function addPackagesToTripBatch(
        int $tripId,
        Collection|array $packages,
        object $user,
        ?string $notes = null,
        ?string $action = null
    ): array
    {
        $packages = $packages instanceof Collection ? $packages : collect($packages);
        $trip = Delivery::find($tripId);
        
        if (!$trip) {
            return ['success' => 0, 'failed' => $packages->count(), 'errors' => ['Trip not found']];
        }

        $successCount = 0;
        $failedCount = 0;
        $errors = [];
        $packageIdsToAdd = [];

        DB::beginTransaction();
        try {
            // Get existing packages in this trip
            $existingPackageIds = DeliveryPackage::where('delivery_id', $tripId)
                ->where('delay_count', 0)
                ->where('has_swap', 0)
                ->where('is_deleted', 0)
                ->pluck('package_id')
                ->toArray();

            // Prepare batch insert data
            $batchData = [];
            $now = now();

            foreach ($packages as $package) {
                // Skip if already in trip
                if (in_array($package->id, $existingPackageIds)) {
                    $successCount++; // Count as success since it's already there
                    continue;
                }

                $packageIdsToAdd[] = $package->id;

                $batchData[] = [
                    'order_id' => $package->order_id,
                    'delivery_id' => $tripId,
                    'payer' => $package->payer,
                    'receiver_phone' => $package->receiver_phone,
                    'receiver_address' => $package->receiver_address,
                    'zone_code' => $package->zone_code,
                    'zone_name' => $package->zone_name,
                    'merchant_id' => $package->merchant_id,
                    'delivery_type' => $package->delivery_type,
                    'product_type' => $package->product_type,
                    'notes' => $notes,
                    'assign_uid' => $action == 'assign' ? $user->id : null,
                    'driver_id' => $trip->driver_id,
                    'package_id' => $package->id,
                    'status_id' => 6, // On Delivery
                    'update_uid' => $user->id,
                    'create_uid' => $user->id,
                    'branch_id' => $user->branch_id,
                    'company_id' => $user->company_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if (!empty($packageIdsToAdd)) {
                // Mark previous attempts as delayed (batch update)
                DeliveryPackage::whereIn('package_id', $packageIdsToAdd)
                    ->where('delay_count', 0)
                    ->where('is_deleted', 0)
                    ->where('delivery_id', '!=', $tripId) // Don't update current trip's records
                    ->update(['delay_count' => 1]);

                // Batch insert new delivery packages
                DeliveryPackage::insert($batchData);
                
                $successCount += count($batchData);

                // Update trip package count once
                $trip->increment('package_count', count($batchData));
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch add packages error: ' . $e->getMessage());
            $errors[] = $e->getMessage();
            $failedCount = $packages->count() - $successCount;
        }

        return [
            'success' => $successCount,
            'failed' => $failedCount,
            'errors' => $errors
        ];
    }

    /**
     * Remove package from trip
     */
    public function removePackageFromTrip(
        int $tripId, 
        int $packageId, 
        object $user,
        bool $markAsSwapped = false
    ): bool
    {
        $deliveryPackage = DeliveryPackage::where('delivery_id', $tripId)
            ->where('package_id', $packageId)
            ->where('delay_count', 0)
            ->where('is_deleted', 0)
            ->first();

        if (!$deliveryPackage) {
            return false; // Package not in trip
        }

        // Update delivery package
        $deliveryPackage->update([
            'is_deleted' => 1,
            'deleted_uid' => $user->id,
            'deleted_datetime' => now(),
            'has_swap' => $markAsSwapped,
            'delay_count' => 1,
        ]);

        // Decrement trip package count
        $trip = Delivery::find($tripId);
        if ($trip && $trip->package_count > 0) {
            $trip->decrement('package_count');
        }

        return true;
    }

    /**
     * Increment trip counts efficiently (for real-time updates)
     */
    public function incrementTripCount(int $tripId, string $countType, int $increment = 1): bool
    {
        $trip = Delivery::find($tripId);
        
        if (!$trip) {
            return false;
        }
 
        $field = match($countType) {
            'delivered' => 'delivered_count',
            'failed' => 'failed_count',
            'failed_with_fee' => 'failed_with_fee_count',  // ADDED
            'on_delivery' => 'package_count',
            default => null
        };
 
        if (!$field) {
            return false;
        }
 
        $trip->increment($field, $increment);
 
        return true;
    }

    /**
     * Check if trip should be completed and update accordingly
     */
    public function completeTripIfNeeded(int $tripId, object $user): bool
    {
        $trip = Delivery::find($tripId);
        
        if (!$trip || $trip->is_completed) {
            return false;
        }
 
        // UPDATED: Include failed_with_fee in total completed
        $totalCompleted = $trip->delivered_count + $trip->failed_count + $trip->failed_with_fee_count;
        
        if ($trip->package_count <= $totalCompleted && $trip->package_count > 0) {
            $trip->update([
                'is_completed' => 1,
                'finished' => 1,
                'status_id' => TrackingStatus::DONE_TRIP->value,
                'finished_datetime' => now(),
                'finished_uid' => $user->id,
                'update_uid' => $user->id,
            ]);
 
            return true;
        }
 
        return false;
    }


    /**
     * Recalculate trip counts from actual package states
     * Use this for data corrections or when counts might be out of sync
     */
    public function recalculateTripCounts(int $tripId, object $user): array
    {
        $packages = DeliveryPackage::where('delivery_id', $tripId)
            ->whereHas('package', function($q) {
                $q->whereIn('status_id', [6, 9, 10, 19]);
            })
            ->whereIn('status_id', [6, 9, 10, 19])
            ->where('has_swap', 0)
            ->where('is_deleted', 0)
            ->where('delay_count', 0)
            ->with('package:id,status_id')
            ->get();
 
        $counts = [
            'delivered' => 0,
            'failed' => 0,
            'failed_with_fee' => 0,  // ADDED
            'on_delivery' => 0,
            'total' => 0
        ];
 
        foreach ($packages as $pkg) {
            if ($pkg->status_id == 9) {
                $counts['delivered']++;
            } elseif ($pkg->status_id == 10) {
                $counts['failed']++;
            } elseif ($pkg->status_id == 19) {
                $counts['failed_with_fee']++;  // ADDED
            } elseif ($pkg->status_id == 6) {
                $counts['on_delivery']++;
            }
        }
 
        $counts['total'] = $counts['delivered'] + $counts['failed'] + $counts['failed_with_fee'] + $counts['on_delivery'];
 
        return $counts;
    }


    /**
     * Get active trip for driver
     */
    public function getActiveTrip(int $driverId, int $companyId): ?Delivery
    {
        return Delivery::where('driver_id', $driverId)
            ->where('company_id', $companyId)
            ->where('finished', 0)
            ->where('is_deleted', 0)
            ->first();
    }

    /**
     * Mark trip as deleted when all packages removed
     */
    public function deleteTripIfEmpty(int $tripId, object $user, string $reason = ''): bool
    {
        $trip = Delivery::find($tripId);
        
        if (!$trip) {
            return false;
        }

        // Only delete if package count is 0
        if ($trip->package_count > 0) {
            return false;
        }

        $trip->update([
            'is_deleted' => 1,
            'deleted_datetime' => now(),
            'deleted_uid' => $user->id,
            'tracking_notes' => ($trip->tracking_notes ?? '') . '|' . ($reason ?: 'All packages removed'),
            'update_uid' => $user->id,
        ]);

        return true;
    }

    /**
     * Batch update package statuses (e.g., driver scans multiple packages)
     */
    public function batchUpdatePackageStatuses(int $tripId, array $packageUpdates, object $user): bool
    {
        if (empty($packageUpdates)) {
            return false;
        }
 
        DB::beginTransaction();
        try {
            $deliveredIncrement = 0;
            $failedIncrement = 0;
            $failedWithFeeIncrement = 0;  // ADDED
 
            foreach ($packageUpdates as $update) {
                $packageId = $update['package_id'];
                $newStatusId = $update['status_id'];
 
                DeliveryPackage::where('delivery_id', $tripId)
                    ->where('package_id', $packageId)
                    ->where('is_deleted', 0)
                    ->where('delay_count', 0)
                    ->update([
                        'status_id' => $newStatusId,
                        'update_uid' => $user->id,
                        'updated_at' => now(),
                    ]);
 
                // Track count changes
                if ($newStatusId == 9) {
                    $deliveredIncrement++;
                } elseif ($newStatusId == 10) {
                    $failedIncrement++;
                } elseif ($newStatusId == 19) {
                    $failedWithFeeIncrement++;  // ADDED
                }
            }
 
            // Update trip counts
            $trip = Delivery::find($tripId);
            if ($trip) {
                $trip->increment('delivered_count', $deliveredIncrement);
                $trip->increment('failed_count', $failedIncrement);
                $trip->increment('failed_with_fee_count', $failedWithFeeIncrement);  // ADDED
 
                $this->completeTripIfNeeded($tripId, $user);
            }
 
            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Batch update package statuses error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Private helper: Find trip with pending packages
     */
    private function findTripWithPendingPackages(int $driverId, int $companyId): ?Delivery
    {
        $lastTrip = Delivery::where('driver_id', $driverId)
            ->where('company_id', $companyId)
            ->where('is_deleted', 0)
            ->orderByDesc('id')
            ->first();

        if (!$lastTrip) {
            return null;
        }

        $hasPendingPackages = DeliveryPackage::where('delivery_id', $lastTrip->id)
            ->where('driver_id', $driverId)
            ->where('status_id', 6)
            ->where('delay_count', 0)
            ->where('has_swap', 0)
            ->where('is_deleted', 0)
            ->whereHas('package', function ($q) {
                $q->where('status_id', 6);
            })
            ->exists();

        return $hasPendingPackages ? $lastTrip : null;
    }

    /**
     * Private helper: Create new trip
     */
    private function createNewTrip(
        int $driverId, 
        int $companyId, 
        int $branchId, 
        string $vehicleType, 
        object $user
    ): Delivery
    {
        $trip = Delivery::create([
            'driver_id' => $driverId,
            'depart_datetime' => now(),
            'package_count' => 0,
            'status_id' => TrackingStatus::ON_DELIVERY_TRIP->value,
            'warehouse_id' => 1,
            'finished' => 0,
            'is_completed' => 0,
            'delivered_count' => 0,
            'failed_count' => 0,
            'failed_with_fee_count' => 0,  // ADDED
            'vehicle_type' => $vehicleType,
            'branch_id' => $branchId,
            'company_id' => $companyId,
            'update_uid' => $user->id,
            'create_uid' => $user->id,
        ]);
 
        Helper::setFleetNumber(
            $branchId,
            'fleet_code_controls',
            'deliveries',
            $trip->id,
            'fleet_tracking_number'
        );
 
        return $trip;
    }

    /**
     * Private helper: Update trip with recalculated counts
     */
    private function updateTripWithCounts(Delivery $trip, array $counts, object $user): void
    {
        $isCompleted = ($counts['on_delivery'] == 0 && $counts['total'] > 0);
        
        $updateData = [
            'delivered_count' => $counts['delivered'],
            'failed_count' => $counts['failed'],
            'failed_with_fee_count' => $counts['failed_with_fee'],  // ADDED
            'package_count' => $counts['total'],
            'is_completed' => $isCompleted,
            'finished' => $isCompleted,
            'update_uid' => $user->id,
        ];
 
        if ($counts['on_delivery'] > 0) {
            $updateData['status_id'] = TrackingStatus::ON_DELIVERY_TRIP->value;
        } else {
            $updateData['status_id'] = TrackingStatus::DONE_TRIP->value;
        }
 
        if ($isCompleted && !$trip->finished) {
            $updateData['finished_datetime'] = now();
            $updateData['finished_uid'] = $user->id;
        }
 
        $trip->update($updateData);
    }

    /**
     * Private helper: Check and update trip completion status
     */
    private function checkAndUpdateTripCompletion(Delivery $trip, object $user): void
    {
        $totalCompleted = $trip->delivered_count + $trip->failed_count;
        $hasPackagesOnDelivery = $trip->package_count > $totalCompleted;

        if ($hasPackagesOnDelivery) {
            // Still has packages on delivery
            if ($trip->status_id != TrackingStatus::ON_DELIVERY_TRIP->value) {
                $trip->update([
                    'status_id' => TrackingStatus::ON_DELIVERY_TRIP->value,
                    'is_completed' => 0,
                    'finished' => 0,
                    'update_uid' => $user->id,
                ]);
            }
        } else {
            // All packages completed
            if (!$trip->is_completed && $trip->package_count > 0) {
                $trip->update([
                    'status_id' => TrackingStatus::DONE_TRIP->value,
                    'is_completed' => 1,
                    'finished' => 1,
                    'finished_datetime' => now(),
                    'finished_uid' => $user->id,
                    'update_uid' => $user->id,
                ]);
            }
        }
    }
}