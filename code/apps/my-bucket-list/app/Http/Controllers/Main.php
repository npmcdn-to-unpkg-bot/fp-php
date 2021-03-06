<?php namespace App\Http\Controllers;

use P;
use Carbon;
use Log;
use App\Item;
use App\State;
use App\Service\ItemService;
use App\User;
use App\Util\Tuple;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use PhpOption\Option as Nullable;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

/**
 * Main Web Handlers
 * @author Luis Atencio
 */
class Main extends Controller {
  
    //GET
    public function __invoke(): View {

        $extractShorName = function ($item) {
            return $item->state->getShortName();
        };

        $allItems = Item::all();
        $newItems = P::pipe(
            P::filter(P::pipe($extractShorName, P::eq('new'))),
            'P::size'             
        );

        return view('main')
            ->with('items', $allItems)
            ->with('remaining_item_count', $newItems($allItems))
            ;
    }

    //POST
    public function newItem(Request $request): RedirectResponse {

        $newItem = Nullable::fromValue($request->input('text'))
                ->reject('')                
                ->filter(P::allPass(['strlen']))                                
                ->map(ItemService::class. '::createNewItem')                
                ->getOrCall(function () {
                    Log::info('New item content not found. Skipping...');
                });

        return redirect('/main')->with('status', 'New item added!');
    }

    //POST
    public function deleteItems(Request $request): RedirectResponse {

        array_map(function ($nul_id) {
            return $nul_id->reject('')
                    ->map('intval')
                    ->filter(P::lt(0))
                    ->map(function ($id) {
                        Log::info("Deleting item with {$id}...");
                        return Item::destroy($id);
                    })                              
                    ->getOrCall(function () {
                        Log::info('Invalid item ID. Skipping delete...');
                        return 0;
                    });
        }, $request->input('items'));

        return redirect('/main')->with('status', 'Items deleted!');
    }

    //GET
    public function deleteItem($id): RedirectResponse {

        $count = Nullable::fromValue($id)
            ->reject('')
            ->map('intval')
            ->filter(P::lt(0))
            ->map(function ($id) {
                Log::info("Deleting item with {$id}...");
                return Item::destroy($id);
            })                              
            ->getOrCall(function () {
                Log::info('Invalid item ID. Skipping delete...');
                return 0;
            });
            
        return redirect('/main')->with('status', "{$count} item deleted!");      
    }

    //POST
    public function completeItem($id): JsonResponse {
        
        $status = Nullable::fromValue($id)
            ->reject('')
            ->map('intval')
            ->filter(P::lt(0))
            ->flatMap(Item::class. '::findNullable')
            ->map(function ($item) {
                return $item->setStateId(State::of('completed')->id)->save();
            })
            ->getOrThrow(new \RuntimeException("Item by ID {$id} not found!"));

        return response()->json([
            'status' => $status,
            'id' => $id
        ]);
    }
}