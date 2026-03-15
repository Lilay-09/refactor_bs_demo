<?php

namespace App\Services;

use App\Models\Delivery;
use App\Models\Package;
use Illuminate\Support\Collection;

interface TripService
{
    /**
     * Update trip status and counts based on current package states
     * 
     * @param int $tripId
     * @param object $user
     * @param bool $forceRecalculate Whether to recalculate counts from scratch
     * @return Delivery|null
     */
    public function updateTripStatus(int $tripId, object $user, bool $forceRecalculate = false): ?Delivery;
 
    /**
     * Find or create an active trip for a driver
     * 
     * @param int $driverId
     * @param int $companyId
     * @param int $branchId
     * @param string $vehicleType
     * @param object $user
     * @return Delivery
     */
    public function findOrCreateActiveTrip(
        int $driverId, 
        int $companyId, 
        int $branchId, 
        string $vehicleType, 
        object $user
    ): Delivery;
 
    /**
     * Add package to trip (used by both admin assignment and driver actions)
     * 
     * @param int $tripId
     * @param Package $package
     * @param object $user
     * @param string|null $notes
     * @param string|null $action Type of action (assign, scan, etc.)
     * @return bool
     */
    public function addPackageToTrip(
        int $tripId, 
        Package $package, 
        object $user, 
        ?string $notes = null,
        ?string $action = null
    ): bool;
 
    /**
     * Add multiple packages to trip in batch
     * 
     * @param int $tripId
     * @param Collection|array $packages
     * @param object $user
     * @param string|null $notes
     * @param string|null $action
     * @return array ['success' => int, 'failed' => int, 'errors' => array]
     */
    public function addPackagesToTripBatch(
        int $tripId,
        Collection|array $packages,
        object $user,
        ?string $notes = null,
        ?string $action = null
    ): array;
 
    /**
     * Remove package from trip
     * 
     * @param int $tripId
     * @param int $packageId
     * @param object $user
     * @param bool $markAsSwapped
     * @return bool
     */
    public function removePackageFromTrip(
        int $tripId, 
        int $packageId, 
        object $user,
        bool $markAsSwapped = false
    ): bool;
 
    /**
     * Increment trip counts (efficient for single package updates)
     * 
     * @param int $tripId
     * @param string $countType delivered|failed|on_delivery
     * @param int $increment
     * @return bool
     */
    public function incrementTripCount(int $tripId, string $countType, int $increment = 1): bool;
 
    /**
     * Check if trip should be completed and update accordingly
     * 
     * @param int $tripId
     * @param object $user
     * @return bool Whether trip was completed
     */
    public function completeTripIfNeeded(int $tripId, object $user): bool;
 
    /**
     * Recalculate trip counts from actual package states (for data corrections)
     * 
     * @param int $tripId
     * @param object $user
     * @return array ['delivered' => int, 'failed' => int, 'on_delivery' => int, 'total' => int]
     */
    public function recalculateTripCounts(int $tripId, object $user): array;
 
    /**
     * Get active trip for driver
     * 
     * @param int $driverId
     * @param int $companyId
     * @return Delivery|null
     */
    public function getActiveTrip(int $driverId, int $companyId): ?Delivery;
 
    /**
     * Mark trip as deleted when all packages removed
     * 
     * @param int $tripId
     * @param object $user
     * @param string $reason
     * @return bool
     */
    public function deleteTripIfEmpty(int $tripId, object $user, string $reason = ''): bool;
 
    /**
     * Batch update package statuses and recalculate trip
     * 
     * @param int $tripId
     * @param array $packageUpdates [['package_id' => int, 'status_id' => int], ...]
     * @param object $user
     * @return bool
     */
    public function batchUpdatePackageStatuses(int $tripId, array $packageUpdates, object $user): bool;
}
