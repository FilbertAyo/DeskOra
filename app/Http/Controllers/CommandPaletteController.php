<?php

namespace App\Http\Controllers;

use App\Services\PortalNavigationIntelligenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommandPaletteController extends Controller
{
    public function __construct(
        private readonly PortalNavigationIntelligenceService $navigation,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->status ?? '') === 'active', 403);

        return response()->json([
            'commands' => $this->navigation->commandsForUser($user),
        ]);
    }
}
