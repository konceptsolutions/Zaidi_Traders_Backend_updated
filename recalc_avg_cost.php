<?php
/**
 * Recalculate items.avg_cost from item_inventory history.
 * Run: php recalc_avg_cost.php
 *
 * Rules:
 * - Purchase in (type 1/2/3): weighted avg using purchase_price (0 = free, dilutes)
 * - Sale return in (type 4): add back at running avg (or return child cost if found)
 * - Out movements: reduce stock only, avg unchanged
 * - Never leave avg at 0 when paid purchases exist
 */

$host = '127.0.0.1';
$db   = 'zaidi_traders_updated';
$user = 'root';
$pass = '';

$mysqli = new mysqli($host, $user, $pass, $db);
if ($mysqli->connect_error) {
    fwrite(STDERR, 'DB connect failed: ' . $mysqli->connect_error . PHP_EOL);
    exit(1);
}
$mysqli->set_charset('utf8mb4');

function round2($n)
{
    return round((float) $n, 2);
}

$itemsRes = $mysqli->query("
    SELECT DISTINCT i.id, i.name, i.avg_cost
    FROM items i
    INNER JOIN item_inventory ii ON ii.item_id = i.id
    WHERE EXISTS (
        SELECT 1 FROM item_inventory x
        WHERE x.item_id = i.id
          AND x.inventory_type_id IN (1,2,3)
          AND IFNULL(x.purchase_price, 0) > 0
    )
    ORDER BY i.id
");

$updated = 0;
$skipped = 0;
$report = [];

while ($item = $itemsRes->fetch_assoc()) {
    $itemId = (int) $item['id'];
    $oldAvg = (float) $item['avg_cost'];

    $rows = $mysqli->query("
        SELECT id, inventory_type_id, quantity_in, quantity_out, purchase_price, return_invoice_id, date
        FROM item_inventory
        WHERE item_id = {$itemId}
        ORDER BY date ASC, id ASC
    ");

    $stock = 0.0;
    $avg = 0.0;

    while ($row = $rows->fetch_assoc()) {
        $qtyIn = (float) $row['quantity_in'];
        $qtyOut = (float) $row['quantity_out'];
        $type = (int) $row['inventory_type_id'];
        $price = $row['purchase_price'];

        if ($qtyIn > 0) {
            $unitCost = null;
            if ($type === 4) {
                // Sale return: prefer stored return cost, else running avg
                $unitCost = $avg;
                if (!empty($row['return_invoice_id'])) {
                    // return_invoice_id on inventory often points to invoice_children_return.id
                    $stmt = $mysqli->prepare('SELECT cost FROM invoice_children_return WHERE id = ? LIMIT 1');
                    $rid = (int) $row['return_invoice_id'];
                    $stmt->bind_param('i', $rid);
                    $stmt->execute();
                    $costRes = $stmt->get_result()->fetch_assoc();
                    $stmt->close();
                    if ($costRes && is_numeric($costRes['cost'])) {
                        $unitCost = (float) $costRes['cost'];
                    }
                }
            } else {
                // Purchases / other ins: use purchase_price (null treated as running avg to avoid wiping)
                if ($price === null || $price === '') {
                    $unitCost = $avg;
                } else {
                    $unitCost = (float) $price;
                }
            }

            $newStock = $stock + $qtyIn;
            if ($newStock > 0) {
                $avg = (($avg * $stock) + ($unitCost * $qtyIn)) / $newStock;
            }
            $stock = $newStock;
        }

        if ($qtyOut > 0) {
            $stock -= $qtyOut;
            if ($stock < 0) {
                $stock = 0;
            }
            // avg unchanged on outbound
        }
    }

    $newAvg = round2($avg);

    // If reconstruction ended at 0 but item has paid purchases, fall back to last paid purchase
    if ($newAvg <= 0) {
        $fallback = $mysqli->query("
            SELECT purchase_price
            FROM item_inventory
            WHERE item_id = {$itemId}
              AND inventory_type_id IN (1,2,3)
              AND IFNULL(purchase_price, 0) > 0
            ORDER BY date DESC, id DESC
            LIMIT 1
        ")->fetch_assoc();
        if ($fallback) {
            $newAvg = round2($fallback['purchase_price']);
        }
    }

    // Only update when meaningfully different
    if (abs($newAvg - $oldAvg) >= 0.01) {
        $stmt = $mysqli->prepare('UPDATE items SET avg_cost = ? WHERE id = ?');
        $stmt->bind_param('di', $newAvg, $itemId);
        $stmt->execute();
        $stmt->close();
        $updated++;
        $report[] = [
            'id' => $itemId,
            'name' => $item['name'],
            'old' => $oldAvg,
            'new' => $newAvg,
            'stock' => $stock,
        ];
    } else {
        $skipped++;
    }
}

echo "Updated: {$updated}, Unchanged: {$skipped}" . PHP_EOL;
foreach ($report as $r) {
    echo sprintf(
        "ID %d | %s | %.2f -> %.2f | stock~%.2f\n",
        $r['id'],
        $r['name'],
        $r['old'],
        $r['new'],
        $r['stock']
    );
}
