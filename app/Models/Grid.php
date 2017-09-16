<?php

namespace Koodilab\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Koodilab\Support\StateManager;

/**
 * Grid.
 *
 * @property int $id
 * @property int $planet_id
 * @property int|null $building_id
 * @property int $x
 * @property int $y
 * @property int|null $level
 * @property int $type
 * @property bool $is_enabled
 * @property \Carbon\Carbon|null $created_at
 * @property \Carbon\Carbon|null $updated_at
 * @property Building|null $building
 * @property Construction $construction
 * @property Planet $planet
 * @property Training $training
 * @property Upgrade $upgrade
 *
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereBuildingId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereIsEnabled($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid wherePlanetId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereX($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Grid whereY($value)
 * @mixin \Eloquent
 */
class Grid extends Model
{
    use Relations\BelongsToBuilding,
        Relations\BelongsToPlanet,
        Relations\HasOneConstruction,
        Relations\HasOneUpgrade,
        Relations\HasOneTraining;

    /**
     * The plain type.
     *
     * @var int
     */
    const TYPE_PLAIN = 0;

    /**
     * The resource type.
     *
     * @var int
     */
    const TYPE_RESOURCE = 1;

    /**
     * The central type.
     *
     * @var int
     */
    const TYPE_CENTRAL = 2;

    /**
     * {@inheritdoc}
     */
    protected $perPage = 30;

    /**
     * {@inheritdoc}
     */
    protected $guarded = [
        'id', 'created_at', 'updated_at',
    ];

    /**
     * {@inheritdoc}
     */
    protected $casts = [
        'is_enabled' => 'bool',
    ];

    /**
     * {@inheritdoc}
     */
    protected static function boot()
    {
        parent::boot();

        static::updated(function (self $grid) {
            app(StateManager::class)->syncPlanet($grid->planet);
        });
    }

    /**
     * Get the constructable buildings.
     *
     * @return Collection
     */
    public function constructableBuildings()
    {
        if ($this->building_id || $this->construction) {
            return new Collection();
        }

        /** @var \Illuminate\Database\Eloquent\Collection $constructedIds */
        $constructedIds = $this->planet->grids()
            ->whereNotNull('building_id')
            ->get(['id', 'building_id'])
            ->groupBy('building_id');

        /** @var \Illuminate\Database\Eloquent\Collection $constructingIds */
        $constructingIds = $this->planet->constructions()
            ->get(['id', 'building_id'])
            ->groupBy('building_id');

        /** @var \Illuminate\Database\Eloquent\Builder $buildings */
        $buildings = Building::whereIn('parent_id', $constructedIds->keys())->defaultOrder();

        if ($this->type == static::TYPE_RESOURCE) {
            $buildings->where('type', Building::TYPE_MINER);
        } else {
            $buildings->whereNotIn('type', [
                Building::TYPE_CENTRAL, Building::TYPE_MINER,
            ]);
        }

        $requiredCount = Building::whereIsRoot()->count();

        return $buildings->get()
            ->filter(function (Building $building) use ($requiredCount, $constructedIds, $constructingIds) {
                if ($constructedIds->count() < $requiredCount) {
                    return !$constructedIds->has($building->id) && !$constructingIds->has($building->id);
                }

                if ($building->limit) {
                    $count = 0;

                    if ($constructedIds->has($building->id)) {
                        $count += $constructedIds->get($building->id)->count();
                    }

                    if ($constructingIds->has($building->id)) {
                        $count += $constructingIds->get($building->id)->count();
                    }

                    return $building->limit > $count;
                }

                return true;
            })
            ->map(function (Building $building) {
                return $building->applyModifiers([
                    'level' => 1,
                    'defense_bonus' => $this->planet->defense_bonus,
                    'construction_time_bonus' => $this->planet->construction_time_bonus,
                ]);
            })
            ->values();
    }

    /**
     * Demolish the building.
     *
     * @param int $level
     */
    public function demolishBuilding($level = null)
    {
        $level = $level ?: $this->level;

        if (empty($level) || !$this->building_id) {
            return;
        }

        if ($this->upgrade) {
            $this->upgrade->delete();
        }

        if ($this->training) {
            $this->training->delete();
        }

        $this->level = max(
            (int) !$this->planet->hasRequiredBuildings($this->id),
            $this->level - $level
        );

        if (!$this->level) {
            $this->level = null;
            $this->is_enabled = true;
            $this->building()->associate(null);
        }

        $this->save();
    }
}
