<?php

namespace Koodilab\Http\Controllers\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Koodilab\Http\Controllers\Controller;
use Koodilab\Models\Building;
use Koodilab\Models\Grid;
use Koodilab\Models\Resource;
use Koodilab\Models\Transformers\Site\ProducerTransformer;

class ProducerController extends Controller
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('player');
    }

    /**
     * Show the producer in json format.
     *
     * @param Grid                $grid
     * @param ProducerTransformer $transformer
     *
     * @return \Illuminate\Http\JsonResponse|array
     */
    public function index(Grid $grid, ProducerTransformer $transformer)
    {
        $this->authorizeProducer($grid);

        return $transformer->transform($grid);
    }

    /**
     * Store a newly created transmute in storage.
     *
     * @param Request  $request
     * @param Grid     $grid
     * @param resource $resource
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request, Grid $grid, Resource $resource)
    {
        $this->authorizeProducer($grid);

        $quantity = (int) $request->get('quantity', 0);

        if ($quantity <= 0) {
            return $this->createBadRequestJsonResponse();
        }

        /** @var \Koodilab\Models\User $user */
        $user = auth()->user();

        if (!$user->hasResource($resource)) {
            return $this->createBadRequestJsonResponse();
        }

        $stock = $grid->planet->findStock($resource);

        if (!$stock->hasQuantity($quantity)) {
            return $this->createBadRequestJsonResponse();
        }

        DB::transaction(function () use ($resource, $user, $stock, $quantity) {
            $user->incrementEnergy(
                round($quantity * $resource->efficiency)
            );

            $stock->decrementQuantity($quantity);
        });
    }

    /**
     * Authorize the producer.
     *
     * @param Grid $grid
     *
     * @return \Illuminate\Http\JsonResponse
     */
    protected function authorizeProducer(Grid $grid)
    {
        $this->authorize('friendly', $grid->planet);

        if (!$grid->building_id) {
            return $this->createBadRequestJsonResponse();
        }

        if ($grid->building->type != Building::TYPE_PRODUCER) {
            return $this->createBadRequestJsonResponse();
        }
    }
}