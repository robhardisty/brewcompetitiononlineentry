<?php
$query_bos_case = "SELECT id,brewJudgingNumber,brewCategory,brewCategorySort,brewSubCategory,brewStyle,brewReceived,brewBosCase,brewBosSlot FROM ".$prefix."brewing WHERE brewBosCase IS NOT NULL ORDER BY brewBosCase ASC, brewBosSlot ASC";
$rows_bos_case = $db_conn->rawQuery($query_bos_case);
$totalRows_bos_case = $db_conn->count;
?>
