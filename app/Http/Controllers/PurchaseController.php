<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryChallan;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use DB;

class PurchaseController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {

        /*
        {
            "purchase_date":"2021-05-26",
            "challan_no":"r2424",
            "supplier_id":2,
            "note":"First Purchase",
            "total_amount":106000,
            "created_by":1,
            "items":[
                {
                    "product_id":1,
                    "small_unit_price":21000,
                    "small_unit_qty":4,
                    "small_unit_sales_price":22000,

                    "big_unit_price":21000 (nullable),
                    "big_unit_qty":4  (nullable),
                    "big_unit_sales_price":22000  (nullable)
                },
                {
                    "product_id":2,
                    "small_unit_price":11000,
                    "small_unit_qty":2,
                    "small_unit_sales_price":12000
                }
            ]
        }

         */

        DB::beginTransaction();
    try{
        $input = $request->all();
        //return response()->json($input,201);
        $validator = \Validator::make($input,[
            'total_amount'=>'required',
            'supplier_id'=>'required',
            'challan_no'=>'required',
            'items.*.product_id'=>'required',
            'items.*.small_unit_qty'=>'required',
        ]);
        if($validator->fails()){
            return response()->json(['errors'=>$validator->errors()],403);
        }
        $input['created_by'] = \Auth::user()->id;
        /* Start Purchase  */
        $purchaseInput = [
            'purchase_date'=>date('Y-m-d',strtotime($request->purchase_date)),
            'challan_no'=>$request->challan_no,
            'supplier_id'=>$request->supplier_id,
            'note'=>$request->note ?? '',
             'total_amount'=>$request->total_amount ,
            'created_by'=>\Auth::user()->id
        ];
        $purchase = Purchase::create($purchaseInput);
        // End Purchase Table Work

        // Start single product purchase
        foreach ($request->items as $singleItem){
            $item = (object) $singleItem;
            $purchaseItemInput = [
                'purchase_id'=>$purchase->id,
                'product_id'=>$item->product_id,
                'big_unit_price'=>$item->big_unit_price??null,
                'small_unit_price'=>$item->small_unit_price,
                'big_unit_qty'=>$item->big_unit_qty??null,
                'small_unit_qty'=>$item->small_unit_qty
            ];

            $purchaseItem = PurchaseItem::create($purchaseItemInput);
            // Start Inventory/Stock
            $existProduct = Inventory::where('product_id',$item->product_id)->first();
            $available_big_unit_qty = $item->big_unit_qty ?? 0;
            $available_small_unit_qty = $item->small_unit_qty;
            if($existProduct!=''){
                $available_big_unit_qty += $existProduct->available_big_unit_qty;
                //$available_big_unit_qty = $available_big_unit_qty + $existProduct->available_big_unit_qty;
                $available_small_unit_qty += $existProduct->available_small_unit_qty;
            }


            $inventoryInput = [
                'product_id'=>$item->product_id,
                'available_big_unit_qty'=>$available_big_unit_qty,
                'available_small_unit_qty'=>$available_small_unit_qty,
                'big_unit_sales_price'=>$item->big_unit_sales_price?? null,
                'small_unit_sales_price'=>$item->small_unit_sales_price,
            ];
            if($existProduct!=''){
               $existProduct->update($inventoryInput);
                $inventory = $existProduct;
            }else{
                $inventory = Inventory::create($inventoryInput);
            }
            $inventoryChallan = [
                'purchase_id'=>$purchase->id,
                'inventory_id'=>$inventory->id,
                'product_id'=>$item->product_id,
                'big_unit_sales_price'=>$item->big_unit_sales_price?? null,
                'small_unit_sales_price'=>$item->small_unit_sales_price,
                'big_unit_cost_price'=>$item->big_unit_price?? null,
                'small_unit_cost_price'=>$item->small_unit_price,
                'big_unit_qty'=>$item->big_unit_qty?? null,
                'small_unit_qty'=>$item->small_unit_qty,
                'available_big_unit_qty'=>$item->big_unit_qty?? null,
                'available_small_unit_qty'=>$item->small_unit_qty,
            ];
            $inventoryChallan = InventoryChallan::create($inventoryChallan);
        }
        DB::commit();
        return response()->json("Successfully Inserted",201);

        }catch(\Exception $e){
        DB::rollback();
            return response()->json(['error'=>$e->errorInfo[2]],500);
        }

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        /*
        {
            "purchase_date":"2021-05-26",
            "challan_no":"r2424",
            "supplier_id":2,
            "note":"First Purchase",
            "total_amount":106000,
            "created_by":1,
            "items":[
                {
                    "product_id":1,
                    "small_unit_price":21000,
                    "small_unit_qty":4,
                    "small_unit_sales_price":22000,

                    "big_unit_price":21000 (nullable),
                    "big_unit_qty":4  (nullable),
                    "big_unit_sales_price":22000  (nullable)
                },
                {
                    "product_id":2,
                    "small_unit_price":11000,
                    "small_unit_qty":2,
                    "small_unit_sales_price":12000
                }
            ]
        }

         */
        DB::beginTransaction();
        try {
            $input = $request->all();
            //return response()->json($input,201);
            $validator = \Validator::make($input, [
                'total_amount' => 'required',
                'supplier_id' => 'required',
                'challan_no' => 'required',
                'items.*.product_id' => 'required',
                'items.*.small_unit_qty' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 403);
            }

            /* Start Purchase  */
            $purchaseInput = [
                'purchase_date'=>date('Y-m-d',strtotime($request->purchase_date)),
                'challan_no'=>$request->challan_no,
                'supplier_id'=>$request->supplier_id,
                'note'=>$request->note ?? '',
                'total_amount'=>$request->total_amount ,
            ];
            $purchase = Purchase::findOrFail($id);
            $purchase->update($purchaseInput);
            // End Purchase Table Work



           //Example: [1,2,3,4];

            $newProductId = [];
            foreach ($request->items as $singleItem) {

                $item = (object)$singleItem;
                $newProductId[] = $item->product_id;
                if(!isset($item->big_unit_qty)){
                    $item->big_unit_qty = 0;
                }
                $purchaseItemInput = [
                    'purchase_id' => $purchase->id,
                    'product_id' => $item->product_id,
                    'big_unit_price' => $item->big_unit_price ?? null,
                    'small_unit_price' => $item->small_unit_price,
                    'big_unit_qty' => $item->big_unit_qty ?? null,
                    'small_unit_qty' => $item->small_unit_qty
                ];

                $purchaseItem = PurchaseItem::where(['purchase_id'=>$id,'product_id'=>$item->product_id])->first();
                if($purchaseItem!=''){
                    $purchaseItem->update($purchaseItemInput);
                }else{
                    $purchaseItem = PurchaseItem::create($purchaseItemInput);
                }

                // Start Inventory/Stock Update
                $inventory = Inventory::where('product_id',$item->product_id)->first();

                $inventoryChallan = InventoryChallan::where([
                    'purchase_id'=>$purchase->id,
                    'inventory_id'=>$inventory->id,
                    'product_id'=>$item->product_id
                ])->first();

                if($inventory!=''){
                                    //                  20                      -           12                     +    15 = 23
                    $available_big_unit_qty = $inventory->available_big_unit_qty;
                    if($item->big_unit_qty>0){
                        $available_big_unit_qty = $inventory->available_big_unit_qty - $inventoryChallan->big_unit_qty + $item->big_unit_qty;
                    }

                    $available_small_unit_qty = $inventory->available_small_unit_qty - $inventoryChallan->small_unit_qty + $item->small_unit_qty;

                    $inventoryInput = [
                        'product_id'=>$item->product_id,
                        'available_big_unit_qty'=>$available_big_unit_qty,
                        'available_small_unit_qty'=>$available_small_unit_qty,
                        'big_unit_sales_price'=>$item->big_unit_sales_price?? null,
                        'small_unit_sales_price'=>$item->small_unit_sales_price,
                    ];
                    $inventory->update($inventoryInput);

                    // Update Inventory Challan
                    $inventoryChallanInput = [
                        'purchase_id'=>$purchase->id,
                        'inventory_id'=>$inventory->id,
                        'product_id'=>$item->product_id,
                        'big_unit_sales_price'=>$item->big_unit_sales_price?? null,
                        'small_unit_sales_price'=>$item->small_unit_sales_price,
                        'big_unit_cost_price'=>$item->big_unit_price?? null,
                        'small_unit_cost_price'=>$item->small_unit_price,
                        'big_unit_qty'=>$item->big_unit_qty?? null,
                        'small_unit_qty'=>$item->small_unit_qty,
                        'available_big_unit_qty'=>$item->big_unit_qty?? null,
                        'available_small_unit_qty'=>$item->small_unit_qty,
                    ];
                    $inventoryChallan->update($inventoryChallanInput);
                }
            }

            //finding missing Items
            $oldProductId = PurchaseItem::where('purchase_id',$id)->pluck('product_id')->toArray();
            $missingProduct = array_diff($oldProductId,$newProductId);
            $missingProductId = array_values($missingProduct);

           $purchaseItems = PurchaseItem::whereIn('product_id',$missingProductId)->where('purchase_id',$id)->get();
            //Inventory Update for Missing Product
            InventoryChallan::whereIn('product_id',$missingProductId)->where('purchase_id',$id)->delete();
            foreach($purchaseItems as $purchaseItem){
                $lastChallan = InventoryChallan::where('product_id',$purchaseItem->product_id)->orderBy('id','DESC')->first();
                $inventory = Inventory::where('product_id',$purchaseItem->product_id)->first();
                if($inventory!=''){
                    $available_big_unit_qty = $inventory->available_big_unit_qty;
                    if($item->big_unit_qty>0){
                        $available_big_unit_qty = $inventory->available_big_unit_qty - $purchaseItem->big_unit_qty;
                    }
                    $available_small_unit_qty = $inventory->available_small_unit_qty - $purchaseItem->small_unit_qty;
                }
                $inventoryInput = [
                    'available_big_unit_qty'=>$available_big_unit_qty,
                    'available_small_unit_qty'=>$available_small_unit_qty,
                    'big_unit_sales_price'=>$lastChallan->big_unit_sales_price??null,
                    'small_unit_sales_price'=>$lastChallan->small_unit_sales_price,
                ];
                $inventory->update($inventoryInput);
                $purchaseItem->delete();

            }



            DB::commit();
            return response()->json("Successfully Updated",201);

            }catch(\Exception $e){
            DB::rollback();
            return response()->json(['error'=>$e->errorInfo[2]],500);
        }

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $purchase = Purchase::findOrFail($id);
            $purchaseItems = PurchaseItem::where('purchase_id',$id)->get();
            foreach($purchaseItems as $items){
                $inventory = Inventory::where('product_id',$items->product_id)->first();
                $availableBigUnitQty = ($inventory->available_big_unit_qty-$items->big_unit_qty)??0;
                $availableSmallUnitQty = ($inventory->available_small_unit_qty-$items->small_unit_qty)??0;
                if($availableBigUnitQty<0){
                    $availableBigUnitQty = 0;
                }
                if($availableSmallUnitQty<0){
                    $availableSmallUnitQty = 0;
                }
                $inventory->update([
                    'available_big_unit_qty'=>$availableBigUnitQty,
                    'available_small_unit_qty'=>$availableSmallUnitQty,

                ]);
                $items->delete();
            }
            //PurchaseItem::where('purchase_id',$id)->delete();
            InventoryChallan::where('purchase_id',$id)->delete();
            $purchase->delete();
            DB::commit();
            return response()->json("Successfully Deleted",200);

        }catch(\Exception $e){
            DB::rollback();
            return response()->json(['error'=>$e->errorInfo[2]],500);
        }

    }
}
