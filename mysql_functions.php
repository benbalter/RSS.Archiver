<?php
$GLOBALS['qCount'] = 0;

function mysql_array($result) {
	$results = array();
	$first = mysql_field_name($result,0);
	while ($row = mysql_fetch_assoc($result)) $results += array($row[$first] => $row);
	return stripslashes_deep($results);
}

function mysql_row_array($result) {
	if (!$result) return array();
	if (mysql_num_rows($result) ==0) return array();
	return stripslashes_deep(mysql_fetch_assoc($result));
}

function mysql_insert($table, $data) {
	$sql = "INSERT INTO `$table` (";
	foreach ($data as $field => $value) $sql .= "`$field`, ";
	$sql = substr($sql,0,strlen($sql)-2) . ") VALUES (";
	foreach ($data as $field => $value) $sql .= "'" . addslashes($value) . "', ";
	$sql = substr($sql,0,strlen($sql)-2) . ")";
	$GLOBALS['qCount']++;
	if (mysql_query($sql)) return mysql_insert_id();
	return false;
}

function mysql_update($table, $data, $query, $connector = "AND") {
	$sql = "UPDATE `$table` SET ";
	foreach ($data as $field => $value) $sql .= "`$field` = '" . addslashes($value) ."', ";
	$sql = substr($sql,0,strlen($sql)-2) . " WHERE ";
	foreach ($query as $field => $value) $sql .= "`$field` = '$value' $connector ";
	$sql = substr($sql,0,strlen($sql)-(strlen($connector)+1));
	$GLOBALS['qCount']++;
	if (mysql_query($sql)) return true;
	return false;
}

function mysql_remove($table, $query=array(), $connector = "AND") {
	$sql = "DELETE FROM `$table` WHERE ";
	foreach ($query as $field => $value) $sql .= "`$field` = '" . addslashes($value) . "' $connector ";
	$sql = substr($sql,0,strlen($sql)-(strlen($connector)+1));
	$GLOBALS['qCount']++;
	if (mysql_query($sql)) return true;
	return false;
}

function mysql_select($table, $query=array(), $connector = "AND", $sort = array(),$limit="") {
	$sql = "SELECT * FROM `$table` ";
	if (sizeof($query)>0) { 
		$sql .= "WHERE ";
		foreach ($query as $field => $value) $sql .= "`$field` = '" . addslashes($value) . "' $connector ";
		$sql = substr($sql,0,strlen($sql)-(strlen($connector)+1));
	}
	if (sizeof($sort) > 0) {
		$sql .= " ORDER BY ";
		foreach ($sort as $field => $direction) {
			$sql .= "$field $direction, ";
		}
		$sql = substr($sql,0,strlen($sql)-2);
	}
	if ($limit != "") $sql .= " LIMIT $limit";
	$result = mysql_query($sql);
	$GLOBALS['qCount']++;
	if (mysql_error()) echo "<p>" . mysql_error() . ": $sql</p>";
	return $result;
}

function stripslashes_deep($value) {
    $value = is_array($value) ?
        array_map('stripslashes_deep', $value) :
        stripslashes($value);
    return $value;
}

function mysql_exists($table,$query=array(),$connector="AND") {
	$result = mysql_select($table,$query,$connector);
	if (mysql_num_rows($result)!=0) return true;
	return false;
}
?>