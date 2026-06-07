<?php
require '../../master.inc.php';
global $db;
$sql = "SELECT e.rowid, e.ref, 
(SELECT SUM(cd.total_ht * (ed.qty / NULLIF(cd.qty, 0))) FROM ".MAIN_DB_PREFIX."expeditiondet ed LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON ed.fk_elementdet = cd.rowid AND ed.element_type = 'commande' WHERE ed.fk_expedition = e.rowid) as expedition_amount_ht,
(
    SELECT COALESCE(SUM(f.total_ht), 0) FROM ".MAIN_DB_PREFIX."facture f
    WHERE EXISTS (
        SELECT 1 FROM ".MAIN_DB_PREFIX."element_element el
        WHERE el.fk_target = f.rowid AND el.targettype = 'facture'
        AND (
            (el.sourcetype = 'shipping' AND el.fk_source = e.rowid)
            OR
            (el.sourcetype = 'commande' AND el.fk_source IN (SELECT fk_source FROM ".MAIN_DB_PREFIX."element_element WHERE fk_target = e.rowid AND targettype = 'shipping' AND sourcetype = 'commande'))
        )
    )
) as total_invoices_ht
FROM ".MAIN_DB_PREFIX."expedition e
";
$res = $db->query($sql);
if ($res) {
    while($row = $db->fetch_object($res)) {
        echo $row->ref . ": SH=" . $row->expedition_amount_ht . " FA=" . $row->total_invoices_ht . "\n";
    }
} else {
    echo $db->lasterror();
}
