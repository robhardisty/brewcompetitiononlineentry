<?php
/**
 * Module:      bos_case.lib.php
 * Description: Best of Show (BoS) case box tracking.
 *
 * Every entry submits 3 bottles. At check-in, the 3rd bottle of each entry is
 * set aside in a dedicated "BoS case box" (24 bottles per box) so that, if the
 * entry medals, staff can pull that exact bottle for the Best of Show round.
 *
 * Each received entry is given a case box number (brewBosCase) and a slot
 * (brewBosSlot, 1-24) within that box. Assignment is a manual batch action run
 * by an admin; entries that already have a slot are never moved, so maps that
 * have already been printed stay accurate.
 */

if (!defined('BOS_CASE_SLOTS')) define('BOS_CASE_SLOTS', 24);
if (!defined('BOS_CASE_COLUMNS')) define('BOS_CASE_COLUMNS', 4);

/**
 * Make sure the brewBosCase and brewBosSlot columns exist on the brewing table.
 *
 * The same columns are added by update/run_update.php, but that script only
 * runs when the installed version changes. Checking here as well means the
 * feature works on an existing site as soon as the files are uploaded.
 *
 * Returns TRUE when both columns exist (or were just added).
 */
function bos_case_ensure_columns($brewing_db_table) {

	require(CONFIG.'config.php');
	$db_conn = new MysqliDb($connection);

	if (!function_exists('check_update')) require_once(LIB.'update.lib.php');

	$ok = TRUE;

	if (!check_update("brewBosCase", $brewing_db_table)) {
		$sql = sprintf("ALTER TABLE `%s` ADD `brewBosCase` INT(11) NULL DEFAULT NULL COMMENT 'BoS case box number holding this entry''s 3rd bottle.';", $brewing_db_table);
		$db_conn->rawQuery($sql);
		if ($db_conn->getLastErrno() !== 0) $ok = FALSE;
	}

	if (!check_update("brewBosSlot", $brewing_db_table)) {
		$sql = sprintf("ALTER TABLE `%s` ADD `brewBosSlot` TINYINT(2) NULL DEFAULT NULL COMMENT 'Slot (1-24) within the assigned BoS case box.';", $brewing_db_table);
		$db_conn->rawQuery($sql);
		if ($db_conn->getLastErrno() !== 0) $ok = FALSE;
	}

	return $ok;

}

/**
 * Work out the next case/slot pair after the given one.
 */
function bos_case_next_position($case, $slot) {
	if ($slot >= BOS_CASE_SLOTS) return array($case + 1, 1);
	return array($case, $slot + 1);
}

/**
 * Assign a case box and slot to every received entry that doesn't have one yet.
 *
 * Fills any remaining slots in the last (highest-numbered) box first, then
 * starts new boxes. Entries are assigned in entry-number order.
 *
 * Returns an array: status (0 = success, otherwise the number of failed writes),
 * assigned (how many entries were given a slot this run), and cases (the total
 * number of case boxes now in use).
 */
function assign_bos_case_slots($brewing_db_table) {

	require(CONFIG.'config.php');
	$db_conn = new MysqliDb($connection);

	$result = array('status' => 0, 'assigned' => 0, 'cases' => 0);

	if (!bos_case_ensure_columns($brewing_db_table)) {
		$result['status'] = 1;
		return $result;
	}

	// Resume point: the last filled slot of the highest-numbered box.
	$row_max = $db_conn->rawQueryOne(sprintf("SELECT MAX(brewBosCase) AS maxCase FROM `%s`", $brewing_db_table));
	$case = (isset($row_max['maxCase'])) ? (int) $row_max['maxCase'] : 0;
	$slot = BOS_CASE_SLOTS; // So that with no boxes yet, the first entry goes to box 1, slot 1.

	if ($case > 0) {
		$row_slot = $db_conn->rawQueryOne(sprintf("SELECT MAX(brewBosSlot) AS maxSlot FROM `%s` WHERE brewBosCase = ?", $brewing_db_table), array($case));
		$slot = (isset($row_slot['maxSlot'])) ? (int) $row_slot['maxSlot'] : 0;
	}

	$rows = $db_conn->rawQuery(sprintf("SELECT id FROM `%s` WHERE brewReceived = 1 AND brewBosCase IS NULL ORDER BY id ASC", $brewing_db_table));

	if ($rows) {
		foreach ($rows as $row) {

			list($case, $slot) = bos_case_next_position($case, $slot);

			$db_conn->where('id', $row['id']);
			if ($db_conn->update($brewing_db_table, array('brewBosCase' => $case, 'brewBosSlot' => $slot))) $result['assigned']++;
			else $result['status']++;

		}
	}

	$result['cases'] = $case;

	return $result;

}

/**
 * Clear every case box assignment so the next "Assign" run starts over at box 1.
 * Only use this before any maps have been printed or bottles boxed.
 */
function reset_bos_case_slots($brewing_db_table) {

	require(CONFIG.'config.php');
	$db_conn = new MysqliDb($connection);

	if (!bos_case_ensure_columns($brewing_db_table)) return 1;

	$db_conn->rawQuery(sprintf("UPDATE `%s` SET brewBosCase = NULL, brewBosSlot = NULL", $brewing_db_table));
	if ($db_conn->getLastErrno() !== 0) return 1;

	return 0;

}
?>
