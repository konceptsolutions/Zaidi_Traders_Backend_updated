<?php

namespace App\Models;

use App\Models\Item;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrderChild extends Model
{
    use HasFactory;
    protected $table = 'purchase_order_children';
    protected $fillable = ['purchase_order_id', 'item_id', 'batch_no', 'manufacturer_id', 'expiry_date', 'quantity', 'pack', 'quoted_rate', 'rate', 'received_quantity', 'returned_quantity', 'purchase_price', 'total', 'remarks'];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id')->with('category', 'subcategory', 'unit', 'strengthunit');
    }

    public function PurchaseOrderTemp()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id')
        // ->orderBy('id', 'desc')
        ;
    }

    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->with('customer');
    }

    public function PurchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class,'purchase_order_id')->with('supplier');
    }

    public function itemName()
    {
        return $this->belongsTo(Item::class, 'item_id', 'id');
    }

    public function supplier()
    {
        return $this->belongsTo(Person::class, 'supplier_id', 'id');
    }

    public function PoNo()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id', 'id')
            ->where('is_received', '=', 1)->select('id', 'po_no', 'request_date', 'is_received', 'person_id')->with('supplier');
    }
    

    

    public function manufacturer()
    {
        return $this->belongsTo(Person::class, 'manufacturer_id', 'id');
    }

    public static function getStoredAveragePrice($itemId)
    {

        $item = Item::where('id', $itemId)->first();

        return $item->avg_cost;
    }

    public static function calculateAveragePriceAndStore($itemId, $qty, $rate, $pack)
    {
        $finalAvg = 0;
        $totAvg = 0;
        $totalQuantity = 0;
        $totalAmount = 0;
        $stockQty = Item::calculateTotalStockQty($itemId);

        $RQty = $stockQty;
        $CQty = 0;

        $purchaseOrders = PurchaseOrderChild::select('item_id', 'quantity', 'rate', 'receive_date', 'pack', 'purchase_order_children.id')
            ->where('item_id', $itemId)
            ->orderBy('purchase_orders.receive_date', 'desc')
            ->orderBy('purchase_orders.id', 'desc')
            ->join('purchase_orders', 'purchase_orders.id', '=', 'purchase_order_children.purchase_order_id')
            ->get();


        $adjustInventoryQty = AdjustInventoryChild::where('item_id', $itemId)
            ->where('quantity_out', '=', 0)
            ->sum('adjust_inventory_children.quantity_in');

        $totaladjustInventory = AdjustInventoryChild::where('item_id', $itemId)
            ->where('quantity_out', '=', 0)
            ->sum('adjust_inventory_children.total');


        $totalQuantity += $adjustInventoryQty;
        $totalAmount += $totaladjustInventory;

        $array = [];
        $i = 0;

        foreach ($purchaseOrders as $purchaseOrder2) {
            if ($totalQuantity < $stockQty) {
                if ($totalQuantity + $purchaseOrder2->quantity > $stockQty) {
                    $CQty = $stockQty - $totalQuantity;
                    $totalQuantity += $stockQty - $totalQuantity;
                    $totalAmount += (($CQty) * $purchaseOrder2->rate);

                    $array[] = array(
                        'type' => 1,
                        'individualAvg' => (($CQty) * $purchaseOrder2->rate) / $CQty,
                        'ptotal' => ($CQty) * $purchaseOrder2->rate,
                        'prate' => $purchaseOrder2->rate,
                        'pqty' => $CQty,
                        'pTotalqty' => $CQty,
                        'totalAmount' => $totalAmount,
                        'totalQuantity' => $totalQuantity,
                        'stockQty' => $stockQty,
                        'averagePrice' => $totalAmount / $totalQuantity,
                        'i' => $i,
                        'id' => $purchaseOrder2->id,
                        'CQty' => $CQty,
                        'difference' => $totalQuantity - $stockQty,
                    );
                } elseif ($totalQuantity + $purchaseOrder2->quantity <= $stockQty) {
                    $totalQuantity += $purchaseOrder2->quantity;
                    $totalAmount += ($purchaseOrder2->quantity * $purchaseOrder2->rate);
                    $totAvg = ($purchaseOrder2->quantity * $purchaseOrder2->rate) / ($purchaseOrder2->quantity);
                    $array[] = array(
                        'type' => 2,
                        'individualAvg' => ($purchaseOrder2->quantity * $purchaseOrder2->rate) / ($purchaseOrder2->quantity),
                        'ptotal' => $purchaseOrder2->quantity * $purchaseOrder2->rate,
                        'prate' => $purchaseOrder2->rate,
                        'pqty' => $purchaseOrder2->quantity,
                        'pTotalqty' => $purchaseOrder2->quantity,
                        'totalAmount' => $totalAmount,
                        'totalQuantity' => $totalQuantity,
                        'stockQty' => $stockQty,
                        'averagePrice' => $totalAmount / $totalQuantity,
                        'i' => $i,
                        'id' => $purchaseOrder2->id,
                    );
                }
                $i += 1;
            } else {
                break;
            }
        }

        // If totalQuantity exists, calculate the average price
        if ($totalQuantity > 0) {
            $averagePrice = $totalAmount / $totalQuantity;
            return $averagePrice;
        }

        return 0;
    }

}

//Husnain Wala tariqa

// foreach ($purchaseOrders as $purchaseOrder2) {
//     if ($totalQuantity  < $stockQty) {
//         // if ($purchaseOrder2->quantity * $purchaseOrder2->pack  < $stockQty) {

//         $totalQuantity += $purchaseOrder2->quantity * $purchaseOrder2->pack;
//         $totalAmount += ($purchaseOrder2->quantity * $purchaseOrder2->rate);
//         if ($remainingQty > 0) {
//             $totalQuantity2 += $remainingQty * $purchaseOrder2->pack;
//             $totalAmount2 += ($remainingQty * $purchaseOrder2->rate);
//             $totalQuantity += $totalQuantity2;
//             $totalAmount += $totalAmount2;
//         }
//         $remainingQty += $stockQty - $totalQuantity;
//         // } elseif ($purchaseOrder2->quantity * $purchaseOrder2->pack  > $stockQty) {

//         //     $purchaseOrder2->quantity * $purchaseOrder2->pack -

//         //         $remainingQty -= $purchaseOrder2->quantity * $purchaseOrder2->pack;

//         //     $totalQuantity += $purchaseOrder2->quantity * $purchaseOrder2->pack;
//         //     $totalAmount += ($purchaseOrder2->quantity * $purchaseOrder2->rate);
//         // }
//     } else {
//         break;
//     }
