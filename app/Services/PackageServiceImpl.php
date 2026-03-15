<?php

namespace App\Services;
use App\Models\Delivery;
use App\Models\Package;
use App\Models\User;
use DataResponse;
use Exception;
use Helper;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PackageServiceImpl implements PackageService
{
    public function __construct(
        protected TripService $tripService
    ) {}
    // Your service methods go here
    public static function deductRowAmountBase($usd, $khr, float $amountUsd, float $exchangeRate = 4000): array {
        // Step 1: Deduct from USD first
        if ($usd >= $amountUsd) {
            $usd -= $amountUsd;
            return [
                'amount_usd' => $usd,
                'amount_khr' => $khr
            ];
        }

        // Step 2: Not enough USD, use all available USD
        $remainingUsd = $amountUsd - $usd;
        $usd = 0;

        // Step 3: Try deduct from KHR equivalent
        $deductKhr = $remainingUsd * $exchangeRate;

        if ($khr >= $deductKhr) {
            $khr -= $deductKhr;
        } else {
            // Not enough KHR, consume all KHR and push USD negative
            $remainingKhr = $deductKhr - $khr;
            $khr = 0;
            $usd -= $remainingKhr / $exchangeRate; // USD goes negative
        }
        return [
            'amount_usd' => $usd,
            'amount_khr' => $khr
        ];
    }


    public static function calculateCodAmtBothCurrencies(
        float $amountUsd,
        float $amountKhr,
        string $userType,
        string $payer,
        int $pkgStatusId,
        float $fees,
        float $taxiFee,
        float $exchangeRate = 4000
    ): array {
        // Define valid payer types per user
        $validPayers = [
            'driver' => 'receiver',
            'merchant' => 'sender',
        ];

        // Reset fees if payer is not valid for the user type
        if (isset($validPayers[$userType]) && $payer !== $validPayers[$userType]) {
            $fees = 0;
        }

        // Add taxi fee if package is delivered
        if ($pkgStatusId === 9) {
            $fees += $taxiFee;
        }
        if($pkgStatusId == 19 && ($amountKhr > 0 || $amountUsd > 0)){
            $fees = 0;
        }

        // if($payer == 'receiver' && $userType == 'merchant' &&$pkgStatusId == 19){
        //     $amountUsd = 0;
        //     $amountKhr = 0;
        //     $fees = 0;
        // }
        // Deduct final fees
        Helper::deductAmountBase($amountUsd, $amountKhr, $fees, $exchangeRate);
        // Log::info($amountUsd.' --- '.$amountKhr);
        return [
            'amount_usd' => number_format($amountUsd, 2, '.', ''),
            'amount_khr' => number_format($amountKhr, 0, '.', ''),
            'all_fees'   => number_format($fees, 2, '.', ''),
        ];
    }

    /**
     * Batch assign selected packages to a driver
     * 
     * @param array $data ['package_ids', 'driver_id', 'notes']
     * @return object DataResponse
     */
    public function batchAssignPackagesToDriver(array $data): object
    {
        $user = UserService::getAuthUser();
        $packageIds = $data['package_ids'] ?? [];
        $driverId = $data['driver_id'] ?? null;
        $notes = $data['notes'] ?? null;
        
        // Validate input
        if (empty($packageIds)) {
            return DataResponse::ValidateFail('No packages selected');
        }
        
        if (!$driverId) {
            return DataResponse::ValidateFail('Driver ID is required');
        }
        
        // Validate driver
        $driver = User::where('is_deleted', 0)
            ->where('delete_account', 0)
            ->where('account_type', 'driver')
            ->find($driverId);
        
        if (!$driver) {
            return DataResponse::ValidateFail('Invalid driver identity');
        }
        
        if ($driver->lock) {
            return DataResponse::ValidateFail(__('messages.info', [
                'info' => 'Driver is currently inactive',
                'khInfo' => 'អ្នកដឹកជញ្ជូនត្រូវបានឈប់ដំណើរការ'
            ]));
        }
        
        DB::beginTransaction();
        try {
            // ✅ Step 1: Load all selected packages and validate
            $packages = Package::where('is_deleted', 0)
                ->whereIn('id', $packageIds)
                ->whereIn('status_id', [1, 5, 6, 7, 10, 19]) // Assignable statuses
                ->get();
            
            if ($packages->isEmpty()) {
                throw new Exception('No valid packages found for assignment');
            }
            
            $validCount = $packages->count();
            $requestedCount = count($packageIds);
            
            if ($validCount < $requestedCount) {
                Log::warning("Batch assign: Selected $requestedCount packages, found $validCount valid packages");
            }
            
            // ✅ Step 2: Group packages by current driver (OPTIMIZED!)
            // Instead of querying for each package, group them first
            $packagesByOldDriver = $packages
                ->filter(fn($pkg) => $pkg->driver_id && $pkg->driver_id != $driverId)
                ->groupBy('driver_id');
            
            // ✅ Step 3: Find all old trips in ONE query (OPTIMIZED!)
            $oldDriverIds = $packagesByOldDriver->keys()->toArray();
            
            $oldTrips = [];
            if (!empty($oldDriverIds)) {
                $oldTrips = Delivery::whereIn('driver_id', $oldDriverIds)
                    ->where('status_id', 14)
                    ->where('finished', 0)
                    ->where('is_deleted', 0)
                    ->get()
                    ->keyBy('driver_id'); // Key by driver_id for easy lookup
            }
            
            // ✅ Step 4: Batch remove packages from old trips (OPTIMIZED!)
            $tripsToCheck = [];
            
            foreach ($packagesByOldDriver as $oldDriverId => $packagesForDriver) {
                if (isset($oldTrips[$oldDriverId])) {
                    $oldTrip = $oldTrips[$oldDriverId];
                    
                    // Remove all packages from this driver's trip
                    foreach ($packagesForDriver as $package) {
                        $removed = $this->tripService->removePackageFromTrip(
                            $oldTrip->id,
                            $package->id,
                            $user,
                            false // markAsSwapped = false (regular reassignment)
                        );
                        
                        if ($removed) {
                            $tripsToCheck[$oldTrip->id] = $oldTrip->id;
                        }
                    }
                }
            }
            
            // ✅ Step 5: Check if old trips should be deleted (empty)
            foreach ($tripsToCheck as $tripId) {
                $this->tripService->deleteTripIfEmpty(
                    $tripId,
                    $user,
                    'Packages reassigned to another driver'
                );
            }
            
            // ✅ Step 6: Find or create active trip for target driver
            $vehicleType = $driver->vehicle_type ?? $packages->first()->vehicle_type ?? 'motor';
            
            $trip = $this->tripService->findOrCreateActiveTrip(
                $driverId,
                $user->company_id,
                $user->branch_id,
                $vehicleType,
                $user
            );
            
            if (!$trip) {
                throw new Exception('Failed to create trip for driver');
            }
            
            // ✅ Step 7: Update all packages with new driver (BATCH UPDATE!)
            Package::whereIn('id', $packages->pluck('id'))
                ->update([
                    'driver_id' => $driverId,
                    'status_id' => 6, // On delivery
                    'assign_driver_datetime' => now(),
                    'updated_at' => now()
                ]);
            
            // Reload packages to get updated data
            $packages = Package::whereIn('id', $packages->pluck('id'))->get();
            
            // ✅ Step 8: Batch add all packages to trip
            $batchNotes = $notes ?? "[$user->id]{$user->account_type} batch assign " . $validCount . " packages (" . Helper::getDateTime() . ")";
            
            $result = $this->tripService->addPackagesToTripBatch(
                $trip->id,
                $packages,
                $user,
                $batchNotes,
                'assign'
            );
            
            if ($result['failed'] > 0) {
                Log::warning('Batch assign: Some packages failed to add to trip', [
                    'succeeded' => $result['succeeded'],
                    'failed' => $result['failed'],
                    'errors' => $result['errors']
                ]);
            }
            
            // ✅ Step 9: Update trip status
            $this->tripService->updateTripStatus($trip->id, $user);
            
            DB::commit();
            
            // Build response message
            $message = __('messages.info', [
                'info' => "Successfully assigned {$result['succeeded']} package(s) to {$driver->username}",
                'khInfo' => "បានប្រគល់កញ្ចប់ {$result['succeeded']} ទៅកាន់ {$driver->username}"
            ]);
            
            $additionalData = [
                'trip_id' => $trip->id,
                'driver_id' => $driverId,
                'driver_name' => $driver->username,
                'total_selected' => $requestedCount,
                'total_succeeded' => $result['succeeded'],
                'total_failed' => $result['failed'],
                'package_count' => $trip->package_count,
                'old_drivers_affected' => count($packagesByOldDriver) // New: Shows how many old drivers affected
            ];
            
            if ($result['failed'] > 0) {
                $additionalData['failed_package_ids'] = array_keys($result['errors']);
                $additionalData['errors'] = $result['errors'];
            }
            
            return DataResponse::JsonResult(
                data: $additionalData,
                message: $message,
                error: false
            );
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error('Batch assign packages error: ' . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return DataResponse::Error(__('messages.error', [
                'info' => 'Failed to assign packages: ' . $e->getMessage()
            ]));
        }
    }
    
    /**
     * Batch assign packages by QR codes (for scanner)
     * 
     * @param array $data ['user', 'qr_codes', 'driver_id', 'notes']
     * @return object DataResponse
     */
    public function batchAssignPackagesByQrCode(array $data): object
    {
        // ✅ FIX: Changed $req to $data
        $qrCodes = $data['qr_codes'] ?? [];
        
        if (empty($qrCodes)) {
            return DataResponse::ValidateFail('No QR codes provided');
        }
        
        // Convert QR codes to package IDs
        $packages = Package::where('is_deleted', 0)
            ->whereIn('qr_code', $qrCodes)
            ->select(['id'])
            ->get();
        
        if ($packages->isEmpty()) {
            return DataResponse::NotFound('No packages found with provided QR codes');
        }
        
        // Create new data array with package IDs
        $data['package_ids'] = $packages->pluck('id')->toArray();
        
        return $this->batchAssignPackagesToDriver($data);
    }

}
