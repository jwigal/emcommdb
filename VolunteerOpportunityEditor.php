<?php
/*******************************************************************************
 *
 *  filename    : VolunteerOpportunityEditor.php
 *  last change : 2003-03-29
 *  website     : http://www.infocentral.org
 *  copyright   : Copyright 2005 Michael Wilt
 *
 *  function    : Editor for donation funds
 *
 *  ChurchInfo is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 ******************************************************************************/

require "Include/Config.php";
require "Include/Functions.php";

// top down design....
// title line
// separator line
// warning line
// first input line: [ Save Changes ] [ Exit ]
// column titles
// first record: text box with order, up, down, delete ; Name, Desc, Active radio buttons
// and so on
// action is change of order number, up, down, delete, Name, Desc, or Active, or Add New


$sAction = $_GET["Action"];
$sOpp = FilterInput($_GET["Opp"],'int');

$sDeleteError = "";

if ($sAction = 'delete' && strlen($sOpp) > 0) {
   $sSQL = "DELETE FROM volunteeropportunity_vol WHERE vol_ID = '" . $sOpp . "'";
   RunQuery($sSQL);
   $sSQL = "DELETE FROM person2volunteeropp_p2vo WHERE p2vo_vol_ID = '" . $sOpp . "'";
   RunQuery($sSQL);

   // we need to now re-order the vol_Order field, since its likely now to be missing a number
   $sSQL = "SELECT * FROM volunteeropportunity_vol ORDER by vol_Order";
   $rsOpps = RunQuery($sSQL);
   $numRows = mysql_num_rows($rsOpps);

   $orderCounter = 1;
   for ($row = 1; $row <= $numRows; $row++) {
      $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
      extract($aRow);
      if ($orderCounter <> $vol_Order) { // found hole, update all records to the end
        $sSQL = "UPDATE volunteeropportunity_vol
                 SET `vol_Order` = '" . $orderCounter . "' " .
	        "WHERE `vol_ID` = '" . $vol_ID . "';";
        RunQuery($sSQL);
      }
      ++$orderCounter;
   }
}

$sPageTitle = gettext("Volunteer Opportunity Editor");

require "Include/Header.php";

// Does the user want to save changes to text fields?
if (isset($_POST["SaveChanges"])) {
   $sSQL = "SELECT * FROM volunteeropportunity_vol";
   $rsOpps = RunQuery($sSQL);
   $numRows = mysql_num_rows($rsOpps);

   for ($iFieldID = 1; $iFieldID <= $numRows; $iFieldID++ ) {
      $aNameFields[$iFieldID] = FilterInput($_POST[$iFieldID . "name"]);

      if ( strlen($aNameFields[$iFieldID]) == 0 ) {
         $aNameErrors[$iFieldID] = true;
	 $bErrorFlag = true;
      } else {
        $aNameErrors[$iFieldID] = false;
      }

      $aDescFields[$iFieldID] = FilterInput($_POST[$iFieldID . "desc"]);

      $aRow = mysql_fetch_array($rsOpps);
      $aIDFields[$iFieldID] = $aRow[0];
   }

   // If no errors, then update.
   if (!$bErrorFlag) {
      for ( $iFieldID=1; $iFieldID <= $numRows; $iFieldID++ ) {
         $sSQL = "UPDATE volunteeropportunity_vol
                  SET `vol_Name` = '" . $aNameFields[$iFieldID] . "',
	              `vol_Description` = '" . $aDescFields[$iFieldID] .
	         "' WHERE `vol_ID` = '" . $aIDFields[$iFieldID] . "';";
         RunQuery($sSQL);
      }
   }
} else {
   if (isset($_POST["AddField"])) { // Check if we're adding a VolOp
      $newFieldName = FilterInput($_POST["newFieldName"]);
      $newFieldDesc = FilterInput($_POST["newFieldDesc"]);
      if (strlen($newFieldName) == 0) {
         $bNewNameError = true;
      } else { // Insert into table
         //  there must be an easier way to get the number of rows in order to generate the last order number.
         $sSQL = "SELECT * FROM volunteeropportunity_vol";
         $rsOpps = RunQuery($sSQL);
         $numRows = mysql_num_rows($rsOpps);
	 $newOrder = $numRows + 1;
	 $sSQL = "INSERT INTO `volunteeropportunity_vol` 
		(`vol_ID` , `vol_Order` , `vol_Name` , `vol_Description`)
        	VALUES ('', '". $newOrder . "', '" . $newFieldName . "', '" . $newFieldDesc . "');";
	 RunQuery($sSQL);
	 $bNewNameError = false;
      }
   }
   // Get data for the form as it now exists..
   $sSQL = "SELECT * FROM volunteeropportunity_vol";

   $rsOpps = RunQuery($sSQL);
   $numRows = mysql_num_rows($rsOpps);

   // Create arrays of Vol Opps.
   for ($row = 1; $row <= $numRows; $row++) {
      $aRow = mysql_fetch_array($rsOpps, MYSQL_BOTH);
      extract($aRow);
      $rowIndex = $vol_Order; // is this dangerous?  the vol_Order field had better be correct.
      $aIDFields[$rowIndex] = $vol_ID;
      $aNameFields[$rowIndex] = $vol_Name;
      $aDescFields[$rowIndex] = $vol_Description;
   }

}

// Construct the form
?>

<script language="javascript">

function confirmDeleteOpp( Opp ) {
var answer = confirm (<?php echo '"' . gettext("Are you sure you want to delete this Vol Opp?") . '"'; ?>)
if ( answer )
	window.location="VolunteerOpportunityEditor.php?Opp=" + Opp + "&Action=delete"
}
</script>

<form method="post" action="VolunteerOpportunityEditor.php" name="OppsEditor">

<table cellpadding="3" width="75%" align="center">

<?php
if ($numRows == 0) {
?>
	<center><h2><?php echo gettext("No volunteer opportunities have been added yet"); ?></h2>
	<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';">
	</center>
<?php
} else { // if an 'action' (up/down arrow clicked, or order was input)
   if ($_GET['row_num'] and $_GET['act']) {
      // cast as int and couple with switch for sql injection prevention for $row_num
      $row_num = (int) $_GET['row_num'];
      $act = $_GET['act'];
      if ($act == 'up' or $act == 'down') {
         $swapRow = $row_num;
         if ($act == 'up') {
            $newRow = --$row_num;
         } else {
            $newRow = ++$row_num;
         }
      }

      $sSQL = "UPDATE volunteeropportunity_vol
               SET vol_Order = '" . $newRow . "' " .
	      "WHERE vol_ID = '" . $aIDFields[$swapRow] . "';";
      RunQuery($sSQL);

      $sSQL = "UPDATE volunteeropportunity_vol
               SET vol_Order = '" . $swapRow . "' " .
	      "WHERE vol_ID = '" . $aIDFields[$newRow] . "';";
      RunQuery($sSQL);

      // now update internal data to match
      $saveID = $aIDFields[$swapRow];
      $saveName = $aNameFields[$swapRow];
      $saveDesc = $aDescFields[$swapRow];

      $aIDFields[$swapRow] = $aIDFields[$newRow];
      $aNameFields[$swapRow] = $aNameFields[$newRow];
      $aDescFields[$swapRow] = $aDescFields[$newRow];

      $aIDFields[$newRow] = $saveID;
      $aNameFields[$newRow] = $saveName;
      $aDescFields[$newRow] = $saveDesc;
   }
} // end if GET	 

?>
<tr><td colspan="5">
<center><b><?php echo gettext("NOTE: ADD, Delete, and Ordering changes are immediate.  Changes to Name or Desc fields must be saved by pressing 'Save Changes'"); ?></b></center>
</td></tr>

<tr><td colspan="5" align="center"><span class="LargeText" style="color: red;">
<?php
if ( $bErrorFlag ) echo gettext("Invalid fields or selections. Changes not saved! Please correct and try again!");
if (strlen($sDeleteError) > 0) echo $sDeleteError;
?>
</span></tr></td>

<tr>
<td colspan="5" align="center">
<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> Name="SaveChanges">
&nbsp;
<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';">
</td>
</tr>

<tr>
<th></th>
<th></th>
<th><?php echo gettext("Name"); ?></th>
<th><?php echo gettext("Description"); ?></th>
</tr>

<?php

for ($row=1; $row <= $numRows; $row++) {
   echo "<tr>";
   echo "<td class=\"LabelColumn\"><b>" . $row . "</b></td>";
   echo "<td class=\"TextColumn\">";
   if ($row == 1) {
      echo "<a href=\"{$_SERVER['PHP_SELF']}?act=na&row_num=" . $row . "\"> <img src=\"Images/Spacer.gif\" border=\"0\" width=\"15\"></a> ";
   } else {
      echo "<a href=\"{$_SERVER['PHP_SELF']}?act=up&row_num=" . $row . "\"> <img src=\"Images/uparrow.gif\" border=\"0\" width=\"15\"></a> ";
   }
   if ($row <> $numRows) {
      echo "<a href=\"{$_SERVER['PHP_SELF']}?act=down&row_num=" . $row . "\"> <img src=\"Images/downarrow.gif\" border=\"0\" width=\"15\"></a>";
   } else {
      echo "<a href=\"{$_SERVER['PHP_SELF']}?act=na&row_num=" . $row . "\"> <img src=\"Images/Spacer.gif\" border=\"0\" width=\"15\"></a>";
   }
    
   echo "<a href=\"{$_SERVER['PHP_SELF']}?act=delete&row_num=" . $row . "\" onclick=confirmDeleteOpp(" . $aIDFields[$row] . ") <img src=\"Images/x.gif\" border=\"0\" width=\"15\"></a></td>";
   ?>

   <td class="TextColumn" align="center">
   <input type="text" name="<?php echo $row . "name"; ?>" value="<?php echo htmlentities(stripslashes($aNameFields[$row]),ENT_NOQUOTES, "UTF-8"); ?>" size="20" maxlength="30">
   <?php
	  
   if ( $aNameErrors[$row] ) {
      echo "<span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . " </span>";
   }
   ?>
   </td>

   <td class="TextColumn">
   <input type="text" Name="<?php echo $row . "desc" ?>" value="<?php echo htmlentities(stripslashes($aDescFields[$row]),ENT_NOQUOTES, "UTF-8"); ?>" size="40" maxlength="100">
   </td>

   </tr>
   <?php
} 
?>

<tr>
<td colspan="5">
<table width="100%">
<tr>
<td width="30%"></td>
<td width="40%" align="center" valign="bottom">
<input type="submit" class="icButton" <?php echo 'value="' . gettext("Save Changes") . '"'; ?> Name="SaveChanges">
&nbsp;
<input type="button" class="icButton" <?php echo 'value="' . gettext("Exit") . '"'; ?> Name="Exit" onclick="javascript:document.location='Menu.php';">
</td>
<td width="30%"></td>
</tr>
</table>
</td>
<td>
</tr>

<tr><td colspan="5"><hr></td></tr>
<tr>
<td colspan="5">
<table width="100%">
<tr>
<td width="15%"></td>
<td valign="top">
<div><?php echo gettext("Name:"); ?></div>
<input type="text" name="newFieldName" size="30" maxlength="30">
<?php if ( $bNewNameError ) echo "<div><span style=\"color: red;\"><BR>" . gettext("You must enter a name.") . "</span></div>"; ?>
&nbsp;
</td>
<td valign="top">
<div><?php echo gettext("Description:"); ?></div>
<input type="text" name="newFieldDesc" size="40" maxlength="100">
&nbsp;
</td>
<td>
<input type="submit" class="icButton" <?php echo 'value="' . gettext("Add New Opportunity") . '"'; ?> name="AddField">
</td>
<td width="15%"></td>
</tr>
</table>
</td>
</tr>
</table>
</form>

<?php require "Include/Footer.php"; ?>
