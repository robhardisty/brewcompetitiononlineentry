<?php
/**
 * Module:      bos_case.lib.php
 * Description: Best of Show (BoS) case box tracking.
 *
 * Every entry submits 3 bottles. At check-in, the 3rd bottle of each entry is
 * set aside in a dedicated "BoS case box" (24 bottles per box) so that, if the
 * entry medals, staff can pull that exact bottle for the Best of Show round.
 *
 * Assignments live in their own table, [prefix]bos_case_slots, so none of the
 * stock BCOE&M tables are changed. Each row ties one entry (brewing.id) to a
 * case box number and a slot (1-24) within that box.
 *
 * Assignment is a manual batch action run by an admin. Entries that already
 * have a slot are never moved, so maps that have already been printed stay
 * accurate.
 */

if (!defined('BOS_CASE_SLOTS')) define('BOS_CASE_SLOTS', 24);
if (!defined('BOS_CASE_COLUMNS')) define('BOS_CASE_COLUMNS', 4);

/**
 * Create the bos_case_slots table if it doesn't exist yet.
 *
 * Called by update/run_update.php and on every use of the feature, so it works
 * on an existing site as soon as the files are uploaded.
 *
 * Returns TRUE when the table exists (or was just created).
 */
function bos_case_ensure_table($prefix) {

	require(CONFIG.'config.php');
	$db_conn = new MysqliDb($connection);

	$sql = sprintf("CREATE TABLE IF NOT EXISTS `%s` (
		`id` INT(11) NOT NULL AUTO_INCREMENT,
		`entry_id` INT(11) NOT NULL COMMENT 'brewing.id of the entry whose 3rd bottle is in this slot',
		`case_num` INT(11) NOT NULL COMMENT 'BoS case box number',
		`slot_num` TINYINT(2) NOT NULL COMMENT 'Slot (1-24) within the case box',
		`assigned` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`),
		UNIQUE KEY `entry_id` (`entry_id`),
		UNIQUE KEY `case_slot` (`case_num`,`slot_num`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;", $prefix."bos_case_slots");

	$db_conn->rawQuery($sql);
	return ($db_conn->getLastErrno() === 0);

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
 * If none of the stored assignments match a current entry (for example, after
 * the competition was archived or entries were purged), the old assignments
 * are cleared first so the new competition starts at box 1.
 *
 * Returns an array: status (0 = success, otherwise the number of failed writes),
 * assigned (how many entries were given a slot this run), and cases (the total
 * number of case boxes now in use).
 */
function assign_bos_case_slots($prefix) {

	require(CONFIG.'config.php');
	$db_conn = new MysqliDb($connection);

	$result = array('status' => 0, 'assigned' => 0, 'cases' => 0);
	$slots_table = $prefix."bos_case_slots";
	$brewing_table = $prefix."brewing";

	if (!bos_case_ensure_table($prefix)) {
		$result['status'] = 1;
		return $result;
	}

	// Leftovers from a previous competition: rows exist but none belong to a current entry.
	$row_total = $db_conn->rawQueryOne(sprintf("SELECT COUNT(*) AS total FROM `%s`", $slots_table));
	if ((int) $row_total['total'] > 0) {
		$row_live = $db_conn->rawQueryOne(sprintf("SELECT COUNT(*) AS live FROM `%s` s INNER JOIN `%s` b ON b.id = s.entry_id", $slots_table, $brewing_table));
		if ((int) $row_live['live'] == 0) $db_conn->rawQuery(sprintf("DELETE FROM `%s`", $slots_table));
	}

	// Resume point: the last filled slot of the highest-numbered box.
	$row_max = $db_conn->rawQueryOne(sprintf("SELECT MAX(case_num) AS maxCase FROM `%s`", $slots_table));
	$case = (isset($row_max['maxCase'])) ? (int) $row_max['maxCase'] : 0;
	$slot = BOS_CASE_SLOTS; // So that with no boxes yet, the first entry goes to box 1, slot 1.

	if ($case > 0) {
		$row_slot = $db_conn->rawQueryOne(sprintf("SELECT MAX(slot_num) AS maxSlot FROM `%s` WHERE case_num = ?", $slots_table), array($case));
		$slot = (isset($row_slot['maxSlot'])) ? (int) $row_slot['maxSlot'] : 0;
	}

	$rows = $db_conn->rawQuery(sprintf("SELECT b.id FROM `%s` b LEFT JOIN `%s` s ON s.entry_id = b.id WHERE b.brewReceived = 1 AND s.id IS NULL ORDER BY b.id ASC", $brewing_table, $slots_table));

	if ($rows) {
		foreach ($rows as $row) {

			list($case, $slot) = bos_case_next_position($case, $slot);

			$data = array('entry_id' => $row['id'], 'case_num' => $case, 'slot_num' => $slot);
			if ($db_conn->insert($slots_table, $data)) $result['assigned']++;
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
function reset_bos_case_slots($prefix) {

	require(CONFIG.'config.php');
	$db_conn = new MysqliDb($connection);

	if (!bos_case_ensure_table($prefix)) return 1;

	$db_conn->rawQuery(sprintf("DELETE FROM `%s`", $prefix."bos_case_slots"));
	if ($db_conn->getLastErrno() !== 0) return 1;

	return 0;

}
?>
