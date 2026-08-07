<?php

namespace App\Models;

use App\Enums\Designation;
use App\Traits\HelperTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HelperTrait;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'type',
        'designation',
        'mobile_no',
        'address',
        'email',
        'password',
        'image',
        'role',
        'details',
        'status'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'designation' => Designation::class,
    ];

    /**
     * Check if user is a professional
     *
     * @return bool
     */
    public function isProfessional(): bool
    {
        return $this->type === 'professional';
    }

    /**
     * Get the time slots assigned to this professional
     */
    public function timeSlots()
    {
        $pivotTable = (new ProfessionalTimeSlot())->getTable();

        return $this->belongsToMany(TimeSlot::class, $pivotTable)
                ->using(ProfessionalTimeSlot::class);
    }

    /**
     * Get the professional time slot pivots (with day info)
     */
    public function professionalTimeSlots()
    {
        return $this->hasMany(ProfessionalTimeSlot::class);
    }

    /**
     * Get the working days for this professional
     */
    public function workingDays()
    {
        return $this->hasMany(ProfessionalWorkingDay::class);
    }

    /**
     * Get the appointment services assigned to this professional
     */
    public function appointmentServices()
    {
        $pivotTable = (new ProfessionalService())->getTable();

        return $this->belongsToMany(AppointmentService::class, $pivotTable)
                    ->using(ProfessionalService::class);
    }

    /**
     * Get the appointments for this professional
     */
    public function professionalAppointments()
    {
        return $this->hasMany(Appointment::class, 'user_id');
    }

    /**
     * Get the roles associated with the user
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_user');
    }

    /**
     * Get the user's role names (memoized per request)
     */
    public function roleNames()
    {
        return once(fn () => $this->roles()->pluck('name'));
    }

    /**
     * Check if user is a super admin
     *
     * @return bool
     */
    public function isSuperAdmin(): bool
    {
        return $this->roleNames()->contains('Super Admin');
    }

    /**
     * Check if user is a super admin
     *
     * @return bool
     */
    public function isVendor(): bool
    {
        return $this->type === 'vendor';
    }

    /**
     * Get all permission names for the user (memoized per request)
     */
    public function loadedPermissionNames()
    {
        return once(fn () => $this->roles()
            ->with('permissions:id,name')
            ->get()
            ->pluck('permissions')
            ->flatten()
            ->pluck('name')
            ->unique()
            ->values());
    }

    /**
     * Check if user has specific permission
     *
     * @param string $permissionName
     * @return bool
     */
    public function hasPermission(string $permissionName): bool
    {
        return $this->loadedPermissionNames()->contains($permissionName);
    }

    /**
     * Get accessible menus for the current user
     *
     */
    public function accessibleMenus()
    {
        if ($this->isSuperAdmin()) {
            return Menu::with(['children'])->whereNull('parent_id')->orderBy('order', 'asc')->get();
        }

        // Get permission names from the cached set (once() memoized) — no extra DB query
        if ($this->isVendor()) {
            $permissionNames = config('vendor.allowed_permissions', []);
        } elseif ($this->isProfessional()) {
            $permissionNames = config('professional.allowed_permissions', []);
        } else {
            $permissionNames = $this->loadedPermissionNames()->toArray();
        }

        $menus = Menu::with(['children.permissions', 'permissions'])
            ->whereNull('parent_id')
            ->orderBy('order', 'asc')
            ->get();

        return $this->filterMenus($menus, $permissionNames);
    }

    /**
     * Filter menus based on user permission names
     *
     * @param Collection $menus
     * @param array $permissionNames
     * @return Collection
     */
    private function filterMenus($menus, array $permissionNames)
    {
        return $menus->filter(function ($menu) use ($permissionNames) {
            $menu->children = $this->filterMenus($menu->children, $permissionNames);

            $hasPermission = $menu->permissions->pluck('name')->intersect($permissionNames)->isNotEmpty();

            return $hasPermission || $menu->children->isNotEmpty();
        });
    }
}
