<?php
require '../../master.inc.php';
global $db;
$sql = "SELECT e.rowid, e.ref, 
(SELECT SUM(cd.total_ht * (ed.qty / NULLIF(cd.qty, 0))) FROM ".MAIN_DB_PREFIX."expeditiondet ed LEFT JOIN ".MAIN_DB_PREFIX."commandedet cd ON ed.fk_elementdet = cd.rowid AND ed.element_type = 'commande' WHERE ed.fk_expedition = e.rowid) as expedition_amount_ht,
(
    (SELECT COALESCE(SUM(f.total_ht), 0)
    FROM ".MAIN_DB_PREFIX."element_element el1
    JOIN ".MAIN_DB_PREFIX."element_element el2 ON el1.fk_source = el2.fk_source AND el1.sourcetype = el2.sourcetype
    JOIN ".MAIN_DB_PREFIX."facture f ON el2.fk_target = f.rowid AND el2.targettype = 'facture'
    WHERE el1.targettype = 'shipping' AND el1.fk_target = e.rowid)
    +
    (SELECT COALESCE(SUM(f.total_ht), 0)
    FROM ".MAIN_DB_PREFIX."element_element el
    JOIN ".MAIN_DB_PREFIX."facture f ON el.fk_target = f.rowid AND el.targettype = 'facture'
    WHERE el.sourcetype = 'shipping' AND el.fk_source = e.rowid)
) as total_invoices_ht
FROM ".MAIN_DB_PREFIX."expedition e
HAVING ABS(COALESCE(expedition_amount_ht, 0) - COALESCE(total_invoices_ht, 0)) > 0.05
";
$res = $db->query($sql);
if ($res) {
    while($row = $db->fetch_object($res)) {
        echo $row->ref . ": SH=" . $row->expedition_amount_ht . " FA=" . $row->total_invoices_ht . "\n";
    }
} else {
    echo $db->lasterror();
}
