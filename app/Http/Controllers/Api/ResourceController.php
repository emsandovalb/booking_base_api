<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Court;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    private CourtController $courts;

    public function __construct(CourtController $courts)
    {
        $this->courts = $courts;
    }

    public function index(Request $request)
    {
        // Temporary compatibility alias: resources are backed by the existing courts table.
        return $this->courts->index($request);
    }

    public function show(Request $request, Court $resource)
    {
        return $this->courts->show($request, $resource);
    }

    public function availability(Request $request, Court $resource)
    {
        return $this->courts->availability($request, $resource);
    }

    public function mine(Request $request)
    {
        return $this->courts->mine($request);
    }

    public function store(Request $request)
    {
        return $this->courts->store($request);
    }

    public function update(Request $request, Court $resource)
    {
        return $this->courts->update($request, $resource);
    }

    public function destroy(Request $request, Court $resource)
    {
        return $this->courts->destroy($request, $resource);
    }
}
