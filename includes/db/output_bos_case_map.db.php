<?php
// Every BoS case box assignment that belongs to a current entry, in box/slot order.
$query_bos_case = sprintf("SELECT b.id,b.brewJudgingNumber,b.brewCategory,b.brewCategorySort,b.brewSubCategory,b.brewStyle,b.brewReceived,s.case_num,s.slot_num FROM `%s` s INNER JOIN `%s` b ON b.id = s.entry_id ORDER BY s.case_num ASC, s.slot_num ASC", $prefix."bos_case_slots", $prefix."brewing");
$rows_bos_case = $db_conn->rawQuery($query_bos_case);
$totalRows_bos_case = $db_conn->count;
?>
