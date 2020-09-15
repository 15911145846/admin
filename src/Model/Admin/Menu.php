<?php

declare (strict_types=1);

namespace HPlus\Admin\Model\Admin;

use \Hyperf\Database\Model\Model;
use \Hyperf\Database\Model\Relations\BelongsToMany;
use Hyperf\DbConnection\Db;
use Hyperf\Utils\Arr;
use Hyperf\Utils\Str;
use HPlus\Admin\Traits\ModelTree;

/**
 * Class Menu.
 *
 * @property int $id
 *
 * @method where($parent_id, $id)
 */
class Menu extends Model
{

    protected $table = 'admin_menu';

    protected $appends = ['route'];

    protected $hidden = ['created_at', 'updated_at'];

    protected $casts = [
        'permission' => 'array',
        'created_at' => "Y-m-d H:i:s",
        'updated_at' => "Y-m-d H:i:s",
    ];
    //AdminBuilder,
    use ModelTree {
        ModelTree::boot as treeBoot;
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['parent_id', 'order', 'is_menu', 'title', 'icon', 'uri', 'permission'];


    public function children()
    {
        return $this->hasMany(get_class($this), 'parent_id')->orderBy('order')->with('children');
    }

    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }

    /**
     * A Menu belongs to many roles.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        $pivotTable = config('admin.database.role_menu_table');

        $relatedModel = config('admin.database.roles_model');

        return $this->belongsToMany($relatedModel, $pivotTable, 'menu_id', 'role_id');
    }

    /**
     * @return array
     */
    public function allNodes(): array
    {
        $connection = config('admin.database.connection') ?: config('database.default');
        $orderColumn = DB::connection($connection)->getQueryGrammar()->wrap($this->orderColumn);
        $byOrder = 'ROOT ASC,' . $orderColumn;
        $query = static::query();
        if (config('admin.check_menu_roles') !== false) {
            $query->with('roles:id,name,slug');
        }
        $query->where('is_menu',1);
        $all_list = $query->selectRaw('*, ' . $orderColumn . ' ROOT')->orderByRaw($byOrder)->get()->toArray();
//        if (config('admin.check_route_permission') !== false) {
//            $permissions = config('admin.database.permissions_model')::query()->get();
//            $all_list = collect($all_list)->map(function ($item) use ($permissions) {
//                $permissionIds = collect(Arr::get($item, 'permission', []))->toArray();
//                $permission = collect($permissions)->filter(function ($permissionItem) use ($permissionIds) {
//                    return in_array($permissionItem->id, $permissionIds);
//                })->map(function ($item) {
//                    return $item->slug;
//                })->flatten()->all();
//                Arr::set($item, "permission", $permission);
//                return $item;
//            })->all();
//        }
        return collect($all_list)->filter(function ($item) {
            return 1;
//            $checkRoles = Admin::user()->visible(Arr::get($item, 'roles', []));
//            $checkPermission = collect(Arr::get($item, 'permission', []))->filter(function ($permissionSlug) {
//                    return Admin::user()->can($permissionSlug);
//                })->count() > 0;
//            return $checkRoles || $checkPermission;
        })->merge([])->toArray();
    }

    /**
     * determine if enable menu bind permission.
     *
     * @return bool
     */
    public function withPermission()
    {
        return (bool)config('admin.menu_bind_permission');
    }

    public function getRouteAttribute()
    {
        return Str::start($this->uri, '/');
    }

}
