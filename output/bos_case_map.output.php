<?php
/**
 * Module:      bos_case_map.output.php
 * Description: Printable map of each Best of Show case box. One page per box,
 *              showing a 6 x 4 grid of slots with the judging number of the
 *              3rd bottle that belongs in each slot, then a lookup list of
 *              every bottle in judging number order showing its box and slot.
 */

// Redirect if directly accessed without authenticated session
if ((!isset($_SESSION['loginUsername'])) || ((isset($_SESSION['loginUsername'])) && ($_SESSION['userLevel'] > 1))) {
    $redirect = "../../403.php";
    $redirect_go_to = sprintf("Location: %s", $redirect);
    header($redirect_go_to);
    exit();
}

require_once (LIB.'bos_case.lib.php');
bos_case_ensure_columns($prefix."brewing");
include (DB.'output_bos_case_map.db.php');

$bc_label_title = isset($label_bos_case_map) ? $label_bos_case_map : "BoS Case Box Map";
$bc_label_box = isset($label_bos_case_box) ? $label_bos_case_box : "BoS Case Box";
$bc_label_slot = isset($label_slot) ? $label_slot : "Slot";
$bc_label_judging = isset($label_judging_number) ? $label_judging_number : "Judging Number";
$bc_label_not_received = isset($label_bos_case_not_received) ? $label_bos_case_not_received : "No longer marked received";
$bc_label_find = isset($label_bos_case_find) ? $label_bos_case_find : "Find a BoS Bottle";
$bc_label_find_sub = isset($label_bos_case_find_sub) ? $label_bos_case_find_sub : "All BoS case box bottles, in judging number order";
$bc_label_none = isset($label_bos_case_none) ? $label_bos_case_none : "No entries have been assigned to a BoS case box yet. On the Admin Dashboard, open Entry Sorting and choose Assign BoS Case Boxes, then print this map again.";

// Group the rows by case box.
$bos_cases = array();
if ($rows_bos_case) {
	foreach ($rows_bos_case as $row) {
		$bos_cases[(int) $row['brewBosCase']][(int) $row['brewBosSlot']] = $row;
	}
}

function bos_case_cell_number($row) {
	if (!empty($row['brewJudgingNumber'])) return readable_judging_number($row['brewCategory'], $row['brewJudgingNumber']);
	return sprintf("%06s", $row['id']);
}

if (empty($bos_cases)) {
	echo sprintf("<div class=\"page-header\"><h2>%s</h2></div><p>%s</p>", $bc_label_title, $bc_label_none);
}

$bos_case_count = count($bos_cases);
$bos_case_i = 0;

foreach ($bos_cases as $case_number => $slots) {

	$bos_case_i++;
	$filled = count($slots);
	$rows_in_grid = (int) ceil(BOS_CASE_SLOTS / BOS_CASE_COLUMNS);

?>
	<div class="page-header">
		<h2><?php echo $bc_label_box." #".$case_number; ?><br><small><em class="text-muted"><?php echo $filled." of ".BOS_CASE_SLOTS." slots filled"; ?></em></small></h2>
	</div>
	<table class="bos-case-map">
	<?php for ($r = 0; $r < $rows_in_grid; $r++) { ?>
		<tr>
		<?php for ($c = 1; $c <= BOS_CASE_COLUMNS; $c++) {
			$slot = ($r * BOS_CASE_COLUMNS) + $c;
			$cell = isset($slots[$slot]) ? $slots[$slot] : FALSE;
		?>
			<td<?php if (!$cell) echo ' class="bos-case-empty"'; ?>>
				<span class="bos-case-slot"><?php echo $slot; ?></span>
				<?php if ($cell) { ?>
				<span class="bos-case-number"><?php echo bos_case_cell_number($cell); ?></span>
				<?php if ($cell['brewReceived'] != 1) { ?><span class="bos-case-flag">*</span><?php } ?>
				<?php } ?>
			</td>
		<?php } ?>
		</tr>
	<?php } ?>
	</table>

	<?php
	$has_flag = FALSE;
	foreach ($slots as $row) if ($row['brewReceived'] != 1) $has_flag = TRUE;
	if ($has_flag) echo "<p class=\"small\">* ".$bc_label_not_received."</p>";
	?>
	<div style="page-break-after:always;"></div>
<?php } // end foreach ($bos_cases)

// Lookup list: every assigned entry sorted by judging number, so staff can find a medal winner's bottle fast.
if (!empty($bos_cases)) {

	$bos_lookup = array();
	foreach ($bos_cases as $case_number => $slots) {
		foreach ($slots as $slot => $row) {
			$number = bos_case_cell_number($row);
			$bos_lookup[] = array('number' => $number, 'case' => $case_number, 'slot' => $slot, 'flag' => ($row['brewReceived'] != 1));
		}
	}
	usort($bos_lookup, function($a, $b) { return strnatcmp($a['number'], $b['number']); });

?>
	<div class="page-header">
		<h2><?php echo $bc_label_find; ?><br><small><em class="text-muted"><?php echo $bc_label_find_sub; ?></em></small></h2>
	</div>
	<div class="bos-case-lookup">
	<table class="table table-bordered table-condensed">
		<thead>
			<tr>
				<th><?php echo $bc_label_judging; ?></th>
				<th><?php echo $bc_label_box; ?></th>
				<th><?php echo $bc_label_slot; ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ($bos_lookup as $item) { ?>
			<tr>
				<td><strong><?php echo $item['number']; if ($item['flag']) echo " *"; ?></strong></td>
				<td><?php echo $item['case']; ?></td>
				<td><?php echo $item['slot']; ?></td>
			</tr>
		<?php } ?>
		</tbody>
	</table>
	</div>
<?php } ?>
