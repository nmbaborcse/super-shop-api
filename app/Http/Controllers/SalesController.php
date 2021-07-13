<?php

namespace App\Http\Controllers;

use App\Models\Inventory;
use App\Models\InventoryChallan;
use App\Models\Product;
use App\Models\ProductSales;
use App\Models\ProductSalesItem;
use App\Models\ProductSalesItemChallan;
use Illuminate\Http\Request;
use DB,Auth;

class SalesController extends Controller
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
            "client_id",total_amount,payable_amount,paid_amount,prev_amount,discount,note,
            "items":[
                    {
                        product_id, big_unit_qty, small_unit_qty,  big_unit_sales_price, small_unit_sales_price
                    },
                    {
                        product_id, big_unit_qty, small_unit_qty,  big_unit_sales_price, small_unit_sales_price
                    }
                ]
        }

         */
        DB::beginTransaction();
        try {

            // Invoice No Generate
            $totalSales = ProductSales::count()+1;
            $serialNo =  sprintf("%04d", $totalSales);
            $invoiceNo = 'SI-'.$serialNo;

            $salesInput = [
                'client_id'=>$request->client_id??null,
                'invoice_no'=>$invoiceNo,
                'sales_date'=>date('Y-m-d'),
                'total_amount'=>$request->total_amount,
                'payable_amount'=>$request->payable_amount,
                'paid_amount'=>$request->paid_amount,
                'prev_amount'=>$request->prev_amount,
                'discount'=>$request->discount,
                'note'=>$request->note,
                'created_by'=>\Auth::user()->id,
            ];
            $sales = ProductSales::create($salesInput);

            foreach ($request->items as $singleItem) {
                $item = (object)$singleItem;

                $inventory = Inventory::where('product_id',$item->product_id)->first();

                $itemInput = [
                    'sales_id'=>$sales->id,
                    'product_id'=>$item->product_id,
                    'big_unit_sales_price'=>$item->big_unit_sales_price??null,
                    'small_unit_sales_price'=>$item->small_unit_sales_price??null,
                    'big_unit_qty'=>$item->big_unit_qty??null,
                    'small_unit_qty'=>$item->small_unit_qty??null,
                ];
                $salesItem = ProductSalesItem::create($itemInput);
                // Check challan wise available product

                // Start Big unit qty challan checking
                if(isset($item->big_unit_qty) && $item->big_unit_qty>0 ){
                    if($inventory->available_big_unit_qty>=$item->big_unit_qty){
                        $bigQty = $item->big_unit_qty;
                        while ($bigQty>0){
                            $inventoryChallan = InventoryChallan::where('product_id',$item->product_id)->where('available_big_unit_qty','>',0)->orderBy('id','ASC')->first();
                            if($bigQty > $inventoryChallan->available_big_unit_qty){
                                $qty = $inventoryChallan->available_big_unit_qty;
                                //$bigQty = $bigQty - $qty;
                                $bigQty -= $qty;
                            }else{
                                $qty = $bigQty;
                                $bigQty = 0;
                            }
                            $inventoryChallan->update([
                                'available_big_unit_qty'=>$inventoryChallan->available_big_unit_qty-$qty
                            ]);
                            ProductSalesItemChallan::create([
                                'sales_item_id'=>$salesItem->id,
                                'inventory_challan_id'=>$inventoryChallan->id,
                                'big_unit_qty'=>$qty
                                // 'small_unit_qty'=>

                            ]);
                        }
                        // End While loop
                        // Update Inventory
                        $inventory->update([
                            'available_big_unit_qty'=>$inventory->available_big_unit_qty-$item->big_unit_qty
                        ]);
                    }
                }
                // End Big unit qty challan

                // Start Small unit qty challan checking
                if(isset($item->small_unit_qty) && $item->small_unit_qty>0 ){
                    if($inventory->available_small_unit_qty>=$item->small_unit_qty){
                        $smallQty = $item->small_unit_qty;

                        while ($smallQty>0){
                            $inventoryChallan = InventoryChallan::where('product_id',$item->product_id)->where('available_small_unit_qty','>',0)->orderBy('id','ASC')->first();
                            if($smallQty>$inventoryChallan->available_small_unit_qty){
                                $qty = $inventoryChallan->available_small_unit_qty;
                                $smallQty-=$qty;
                            }else{
                                $qty = $smallQty;
                                $smallQty = 0;
                            }
                            $inventoryChallan->update([
                                'available_small_unit_qty'=>$inventoryChallan->available_small_unit_qty-$qty
                            ]);
                            ProductSalesItemChallan::create([
                                'sales_item_id'=>$salesItem->id,
                                'inventory_challan_id'=>$inventoryChallan->id,
                                //'big_unit_qty'=>$qty
                                'small_unit_qty'=>$qty,

                            ]);
                        }
                        // End While loop
                        // Update Inventory
                        $inventory->update([
                            'available_small_unit_qty'=>$inventory->available_small_unit_qty-$item->small_unit_qty
                        ]);
                    }
                }
                // End Big unit qty challan


            }

            DB::commit();
            return response()->json("Successfully Submitted", 201);

        }catch(Exception $e){
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
           "client_id",total_amount,payable_amount,paid_amount,prev_amount,discount,note,
           "items":[
                   {
                       product_id, big_unit_qty, small_unit_qty,  big_unit_sales_price, small_unit_sales_price
                   },
                   {
                       product_id, big_unit_qty, small_unit_qty,  big_unit_sales_price, small_unit_sales_price
                   }
               ]
       }

        */
        DB::beginTransaction();
        try {



            $salesInput = [
                'client_id'=>$request->client_id??null,
                'total_amount'=>$request->total_amount,
                'payable_amount'=>$request->payable_amount,
                'paid_amount'=>$request->paid_amount,
                'prev_amount'=>$request->prev_amount,
                'discount'=>$request->discount,
                'note'=>$request->note
            ];
            $sales = ProductSales::findOrFail($id);
            $sales->update($salesInput);

            $newProductId = [];
            foreach ($request->items as $singleItem) {
                $item = (object)$singleItem;
                $newProductId[] = $item->product_id;

                $inventory = Inventory::where('product_id',$item->product_id)->first();

                $itemInput = [
                    'sales_id'=>$sales->id,
                    'product_id'=>$item->product_id,
                    'big_unit_sales_price'=>$item->big_unit_sales_price??null,
                    'small_unit_sales_price'=>$item->small_unit_sales_price??null,
                    'big_unit_qty'=>$item->big_unit_qty??null,
                    'small_unit_qty'=>$item->small_unit_qty??null,
                ];
                $salesItem = ProductSalesItem::where(['product_id'=>$item->product_id,'sales_id'=>$sales->id])->first();
                if($salesItem!=''){
                    //Inventory Update
                    if($inventory!='') {
                        //                  20                      -           12                     +    15 = 23
                        $available_big_unit_qty = $inventory->available_big_unit_qty;
                        if (isset($item->big_unit_qty) && $salesItem->big_unit_qty > 0) {
                            $available_big_unit_qty = $inventory->available_big_unit_qty + $salesItem->big_unit_qty;
                        }

                        $available_small_unit_qty = $inventory->available_small_unit_qty + $salesItem->small_unit_qty;

                        $inventoryInput = [
                            'available_big_unit_qty' => $available_big_unit_qty,
                            'available_small_unit_qty' => $available_small_unit_qty,
                        ];
                        $inventory->update($inventoryInput);
                    }
                    // product sales update
                    $salesItem->update($itemInput);
                }else{
                    $salesItem = ProductSalesItem::create($itemInput);
                }



                //Return product to challan
                $salesItemChallans = ProductSalesItemChallan::where('sales_item_id',$salesItem->id)->get();
                foreach($salesItemChallans as $salesItemChallan){
                   $inventoryChallanUpdate =  InventoryChallan::where('id',$salesItemChallan->inventory_challan_id)->first();
                    $inventoryChallanUpdate->update([
                        'available_big_unit_qty'=>$inventoryChallanUpdate->available_big_unit_qty+($salesItemChallan->big_unit_qty??0),
                        'available_small_unit_qty'=>$inventoryChallanUpdate->available_small_unit_qty+($salesItemChallan->small_unit_qty??0),
                    ]);
                    $salesItemChallan->delete();
                }


                // Check challan wise available product

                // Start Big unit qty challan checking
                if(isset($item->big_unit_qty) && $item->big_unit_qty>0 ){
                    if($inventory->available_big_unit_qty>=$item->big_unit_qty){
                        $bigQty = $item->big_unit_qty;
                        while ($bigQty>0){
                            $inventoryChallan = InventoryChallan::where('product_id',$item->product_id)->where('available_big_unit_qty','>',0)->orderBy('id','ASC')->first();
                            if($bigQty > $inventoryChallan->available_big_unit_qty){
                                $qty = $inventoryChallan->available_big_unit_qty;
                                //$bigQty = $bigQty - $qty;
                                $bigQty -= $qty;
                            }else{
                                $qty = $bigQty;
                                $bigQty = 0;
                            }
                            $inventoryChallan->update([
                                'available_big_unit_qty'=>$inventoryChallan->available_big_unit_qty-$qty
                            ]);
                            ProductSalesItemChallan::create([
                                'sales_item_id'=>$salesItem->id,
                                'inventory_challan_id'=>$inventoryChallan->id,
                                'big_unit_qty'=>$qty
                                // 'small_unit_qty'=>

                            ]);
                        }
                        // End While loop
                        // Update Inventory
                        $inventory->update([
                            'available_big_unit_qty'=>$inventory->available_big_unit_qty-$item->big_unit_qty
                        ]);
                    }
                }
                // End Big unit qty challan

                // Start Small unit qty challan checking
                if(isset($item->small_unit_qty) && $item->small_unit_qty>0 ){
                    if($inventory->available_small_unit_qty>=$item->small_unit_qty){
                        $smallQty = $item->small_unit_qty;

                        while ($smallQty>0){
                            $inventoryChallan = InventoryChallan::where('product_id',$item->product_id)->where('available_small_unit_qty','>',0)->orderBy('id','ASC')->first();
                            if($smallQty>$inventoryChallan->available_small_unit_qty){
                                $qty = $inventoryChallan->available_small_unit_qty;
                                $smallQty-=$qty;
                            }else{
                                $qty = $smallQty;
                                $smallQty = 0;
                            }
                            $inventoryChallan->update([
                                'available_small_unit_qty'=>$inventoryChallan->available_small_unit_qty-$qty
                            ]);
                            ProductSalesItemChallan::create([
                                'sales_item_id'=>$salesItem->id,
                                'inventory_challan_id'=>$inventoryChallan->id,
                                //'big_unit_qty'=>$qty
                                'small_unit_qty'=>$qty,

                            ]);
                        }
                        // End While loop
                        // Update Inventory
                        $inventory->update([
                            'available_small_unit_qty'=>$inventory->available_small_unit_qty-$item->small_unit_qty
                        ]);
                    }
                }
                // End Big unit qty challan
            }

            //finding missing Items
            $oldProductId = ProductSalesItem::where('sales_id',$id)->pluck('product_id')->toArray();
            $missingProduct = array_diff($oldProductId,$newProductId);
            $missingProductId = array_values($missingProduct);

            $salesItems = ProductSalesItem::whereIn('product_id',$missingProductId)->get();
            foreach($salesItems as $sItem){
                $inventory = Inventory::where('product_id',$sItem->product_id)->first();
                if($inventory!=''){
                    $available_big_unit_qty = $inventory->available_big_unit_qty;
                    if(isset($item->big_unit_qty) && $item->big_unit_qty>0){
                        $available_big_unit_qty = $inventory->available_big_unit_qty + $sItem->big_unit_qty;
                    }
                    $available_small_unit_qty = $inventory->available_small_unit_qty + $sItem->small_unit_qty;
                }
                $inventoryInput = [
                    'available_big_unit_qty'=>$available_big_unit_qty,
                    'available_small_unit_qty'=>$available_small_unit_qty,
                ];
                $inventory->update($inventoryInput);
                $sItem->delete();
            }


            DB::commit();
            return response()->json("Successfully Submitted", 201);

        }catch(Exception $e){
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
        //
    }
}
