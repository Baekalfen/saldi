<?php
#----------------- debitor/ordrefunc.php --------- 2009.10.20 ----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af The Free Software Foundation; enten i version 2
// af denne licens eller en senere version efter eget valg
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.fundanemt.com/gpl_da.html
//
// Copyright (c) 2004-2009 DANOSOFT ApS
// ----------------------------------------------------------------------

function levering($id,$hurtigfakt,$genfakt,$webservice) {

global $regnaar;
global $levdate;
global $lev_nr;
global $db;

$fp=fopen("../temp/ordrelev.log","a");
transaktion("begin");

$q = db_select("select lev_nr from batch_salg where ordre_id = $id order by lev_nr",__FILE__ . " linje " . __LINE__);
while ($r=db_fetch_array($q)) {
	if ($lev_nr<=$r['lev_nr']){
		$lev_nr=$r['lev_nr']+1;
	}
}
if (!$lev_nr) {$lev_nr=1;}
		
$query = db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
$row =db_fetch_array($query);
$ref=$row['ref'];
$levdate=$row['levdate'];
$fakturadate=$row['fakturadate'];
$art=$row['art'];
$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);
if ($row =db_fetch_array($query)) {
#	$year=substr(str_replace(" ","",$row['box2']),-2); #aendret 060308 - grundet mulighed for fakt i aar 2208
	$year=trim($row['box2']);
	$aarstart=str_replace(" ","",$year.$row['box1']);
#	$year=substr(str_replace(" ","",$row['box4']),-2);
	$year=trim($row['box4']);
	$aarslut=str_replace(" ","",$year.$row['box3']);
}
if ($hurtigfakt && $fakturadate && $fakturadate != $levdate) {
	db_modify("update ordrer set levdate = fakturadate where id = $id",__FILE__ . " linje " . __LINE__);
}
$r=db_fetch_array(db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__));
if (!$r['levdate']){
	print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
#	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	exit;
} else {
	if ($r['levdate']<$r['ordredate']) {
		 print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	list ($year, $month, $day) = explode('-', $r['levdate']);
	$year=trim($year);
	$ym=$year.$month;
	if (!$webservice && ($ym<$aarstart||$ym>$aarslut)) {
		print "<BODY onLoad=\"javascript:alert('Leveringsdato uden for regnskabs&aring;r')\">";
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		 exit;
	}
	if ($hurtigfakt=='on' && !$fakturadate) {
		 print "<meta http-equiv=\"refresh\" content=\"0;URL=fakturadato.php?id=$id&returside=levering.php&hurtigfakt=on\">";
#		include("fakturadato.php");
#		fakturadato($id);
		exit;
	}
	if ($fejl==0){
		$fakturanr=1;
		$x=0;

		$query = db_select("select * from ordrelinjer where ordre_id = '$id' and samlevare = 'on'",__FILE__ . " linje " . __LINE__);
		while ($row =db_fetch_array($query)){
			if ($row[leveres]!=0) samlevare($row[id], $row['vare_id'], $row['leveres']);
		}
		$query = db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
		while ($row =db_fetch_array($query)){
			if (($row[posnr]>0)&&(strlen(trim(($row[varenr])))>0)){
				$x++;
				$linje_id[$x]=$row[id];
				$kred_linje_id[$x]=$row[kred_linje_id];
				$vare_id[$x]=$row['vare_id'];
				$varenr[$x]=$row['varenr'];
				$antal[$x]=$row[antal];
				$leveres[$x]=$row[leveres];
				$pris[$x]=$row[pris];
				$rabat[$x]=$row[rabat];
				$nettopris[$x]=$row[pris]-($row[pris]*$row[rabat]/100);
				$serienr[$x]=trim($row['serienr']);
				$posnr[$x]=$row[posnr];
				if ($hurtigfakt=='on') $leveres[$x]=$antal[$x];
			}
		}
		$linjeantal=$x;
		for ($x=1; $x<=$linjeantal; $x++) {
			$tidl_lev=0;
			$query = db_select("select antal from batch_salg where linje_id = $linje_id[$x]",__FILE__ . " linje " . __LINE__);
			while ($row =db_fetch_array($query)) {
				$tidl_lev=$tidl_lev+$row[antal];
			} 
			if ($hurtigfakt=='on') $leveres[$x]=$antal[$x]-$tidl_lev;
			if (($antal[$x]>0)&&($antal[$x]<$leveres[$x]+$tidl_lev)) {
				print "<BODY onLoad=\"javascript:alert('Der er sat for meget til levering (pos nr. $posnr[$x])')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if (($leveres[$x]>0)&&($serienr[$x])) {
				$sn_antal[$x]=0;
				$query = db_select("select * from serienr where salgslinje_id = '$linje_id[$x]' and batch_salg_id=0",__FILE__ . " linje " . __LINE__);
				while ($row =db_fetch_array($query)) {$sn_antal[$x]=$sn_antal[$x]+1; }
			 if ($leveres[$x]!=$sn_antal[$x]) {
					 print "<BODY onLoad=\"javascript:alert('Der er sat $leveres[$x] til levering men valgt $sn_antal[$x] serienumre (pos nr: $posnr[$x])')\">";
					 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					 exit;
				}
			}	
			if (($leveres[$x]<0)&&($serienr[$x])) {
				$sn_antal[$x]=0;
				$query = db_select("select * from serienr where salgslinje_id = $kred_linje_id[$x]*-1",__FILE__ . " linje " . __LINE__);
				while ($row =db_fetch_array($query)) {
					$sn_antal[$x]=$sn_antal[$x]+1;
				}
			 if ($leveres[$x]+$sn_antal[$x]!=0){
					$tmp=$leveres[$x]*-1;
					print "<BODY onLoad=\"javascript:alert('Der er sat $tmp til returnering men valgt $sn_antal[$x] serienumre (pos nr: $posnr[$x])')\">";
					 print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
					 exit;
				}
			}	
			if ($leveres[$x]<0 && $art == 'DK') {
				 $tidl_lev=0;
				 $query = db_select("select * from batch_kob where linje_id = '$linje_id[$x]' and ordre_id=$id",__FILE__ . " linje " . __LINE__);
				 while($row = db_fetch_array($query)) $tidl_lev=$tidl_lev-$row[antal];
				 if ($leveres[$x]>$tidl_lev+$antal[$x]) $leveres[$x]=$antal[$x]-$tidl_lev;
			}
		}
		for ($x=1; $x<=$linjeantal; $x++)	{
			$sn_start=0;
			$query = db_select("select * from varer where id='$vare_id[$x]'",__FILE__ . " linje " . __LINE__);
			$row =db_fetch_array($query);
			$kostpris[$x]=$row[kostpris];
			$gruppe[$x]=$row[gruppe];
			if ($row[beholdning]) {$beholdning[$x]=$row[beholdning];}
			else {$beholdning[$x]=0;}
			$beholdning[$x]=$beholdning[$x]-$leveres[$x];
#			if (trim($row['samlevare'])=='on') {
#				for ($a=1; $a<=$leveres[$x]; $a++) samlevare($vare_id[$x], $linje_id[$x]);
#			}
			if (!$gruppe[$x]) {
				print "<BODY onLoad=\"javascript:alert('Vare tilhrer ikke nogen varegruppe - kontroller vare og indstillinger! (pos nr: $posnr[$x])')\">";
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			if (($vare_id[$x])&&($leveres[$x]!=0)) {
				linjeopdat($id, $gruppe[$x], $linje_id[$x], $beholdning[$x], $vare_id[$x], $leveres[$x], $pris[$x], $nettopris[$x], $rabat[$x], $row['samlevare'], $x, $posnr[$x], $serienr[$x], $kred_linje_id[$x]);
			}
		}
	}
}

transaktion("commit");
return("Levering OK");

} #endfunc levering

#############################################################################################

function linjeopdat($id ,$gruppe, $linje_id, $beholdning, $vare_id, $antal, $pris, $nettopris, $rabat, $samlevare, $linje_nr, $posnr, $serienr, $kred_linje_id){
	global $fp;
	global $levdate;
	global $fakturadate;
	global $sn_id;
	global $art;
	global $ref;
	global $lev_nr;

	$query = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
	if ($row =db_fetch_array($query)){
		$box1=trim($row[box1]); $box2=trim($row[box2]); $box3=trim($row[box3]); $box4=trim($row[box4]); $box8=trim($row[box8]); $box9=trim($row[box9]);
	} else {
		$r=db_fetch_array(db_select("select posnr from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__));
		print "<BODY onLoad=\"javascript:alert('Varegruppe ikke opsat korrekt, pos nr $r[posnr]')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	} 
	if (!$box3 || !$box4) {
		$fejltekst="Varegruppe $gruppe mangler kontonummer for varek&oslash;b og/eller varesalg (Indstillinger -> Varegrp)";
		print "<BODY onLoad=\"javascript:alert('$fejltekst')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
	}			
	if (($box8!='on')||($samlevare=='on')){
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) values (0, $vare_id, $linje_id, '$levdate', $id, $antal, '$pris', '$lev_nr')",__FILE__ . " linje " . __LINE__);
	}
	else {
		db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
		db_modify("update varer set beholdning=$beholdning where id='$vare_id'",__FILE__ . " linje " . __LINE__);
		if ($box9=='on') {
			if ($antal<0) {krediter($id, $levdate, $beholdning, $vare_id, $antal*-1, $pris, $linje_id, $serienr, $kred_linje_id);} 
			else {batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_n, $posnr, $serienr, $lager);}
		} else {
			db_modify("update ordrelinjer set bogf_konto=$box4 where id='$linje_id'",__FILE__ . " linje " . __LINE__);
			db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, pris, lev_nr) values (0, $vare_id, $linje_id, '$levdate', $id, $antal, '$pris', '$lev_nr')",__FILE__ . " linje " . __LINE__);
		}
	}
}
#####################

function batch_salg_lev($id, $levdate, $fakturadate, $beholdning, $vare_id, $antal, $pris, $nettopris, $linje_id, $linje_nr, $posnr, $serienr, $lager){
	global $sn_id;
	global $lev_nr;		
	global $fp;
	
	$rest=$antal;
	$sn_start=0;
	$kobsbelob=0;
	$a=0;
	$res_sum=0;
	$res_linje_antal=0;


	if (!db_fetch_array(db_select("select * from reservation where linje_id = $linje_id",__FILE__ . " linje " . __LINE__))) batch($linje_id);  #Hvis der ikke manuelt er reserveret varer tages automatisk fra den ldste indkbsordre
	$query = db_select("select * from reservation where linje_id = $linje_id",__FILE__ . " linje " . __LINE__); #Finder reserverede varer som er koebt hjem
	while (($row =db_fetch_array($query))&&($res_sum<$antal)) {
		$x++;
		$batch_kob_id[$x]=$row[batch_kob_id];
		$res_antal[$x]=$row[antal];
		$res_sum=$res_sum+$row[antal];
		$lager=$row[lager];
		if ($res_sum>=$antal){  #Indsat 091106 for 
			$diff[$x]=$res_sum-$antal;
			$res_antal[$x]=$res_antal[$x]-$diff[$x];
			$res_sum=$antal;
		}
	}
	$res_linje_antal=$x;
	$rest=$rest-$res_sum;

	if ($rest>0) {  #Hvis ikke alle varer er koebt hjem eller reserveret saaaa....	
		$query = db_select("select * from reservation where batch_salg_id = $linje_id*-1 and antal = $rest",__FILE__ . " linje " . __LINE__); #Finder reserverede varer som er bestilt hos lev.
		$row=db_fetch_array($query);
		if ($row['linje_id']) {
			db_modify("insert into batch_salg(vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($vare_id, $linje_id, '$levdate', $id, $rest, '$lev_nr')",__FILE__ . " linje " . __LINE__);
			$query = db_select("select id from batch_salg where vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$rest and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$row =db_fetch_array($query);
			$batch_salg_lev_id=$row['id']; 
#20090620 Rettet fra batch_salg_id til batch_salg_lev_id - kundeordre 1761 i saldi_2_20090620-1204.sdat
			db_modify("update reservation set batch_salg_id='$batch_salg_lev_id' where batch_salg_id=$linje_id*-1",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $lager, $rest);	
		}
		else {
			print "<BODY onLoad=\"javascript:alert('Reserveret antal stemmer ikke overens med antal til levering (pos nr: $posnr)')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
	else $rest=$antal;

	for ($x=1; $x<= $res_linje_antal; $x++) {
		$query = db_select("select * from batch_kob where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
		if ($row =db_fetch_array($query)) {
			$kob_antal=$row[antal];
			$kob_rest=$row[rest];
			$kob_ordre_id=$row[ordre_id];
			$kob_pris=$row[pris];
			$lager=$row[lager];
			if (!$kob_pris) {$kob_pris='0';}
			$kob_rest=$kob_rest-$res_antal[$x];
			db_modify("update batch_kob set rest=$kob_rest where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($batch_kob_id[$x], $vare_id, $linje_id, '$levdate', $id, $res_antal[$x], '$lev_nr')",__FILE__ . " linje " . __LINE__);
			$query2 = db_select("select id from batch_salg where batch_kob_id=$batch_kob_id[$x] and vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=$res_antal[$x] and	lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__);
			$row2 =db_fetch_array($query2);
			if ($serienr) {db_modify("update serienr set batch_salg_id=$row2[id] where salgslinje_id=$linje_id",__FILE__ . " linje " . __LINE__);}
			db_modify("update ordrelinjer set leveres='0' where id='$linje_id'",__FILE__ . " linje " . __LINE__);
			if ($diff[$x]) db_modify("update reservation set antal='$diff[$x]' where linje_id='$linje_id' and vare_id='$vare_id' and batch_kob_id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			else db_modify("delete from reservation where linje_id='$linje_id' and vare_id='$vare_id' and batch_kob_id='$batch_kob_id[$x]'",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $lager, $rest);	
			$rest=0;
		}
		else {
			print "<BODY onLoad=\"javascript:alert('Hmm - Indkbsordre kan ikke findes - levering kan ikke foretages - Kontakt systemadministrator')\">";
			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	}
}

###############################################################
function lagerstatus ($vare_id, $lager, $antal) {
	global $ref;

	if (!$lager) {
		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$ref'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr'];}
		}
	}
	$lager=$lager*1;
	
	$query = db_select("select * from lagerstatus where vare_id='$vare_id' and lager='$lager'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$tmp=$row[beholdning]-$antal;
		db_modify("update lagerstatus set beholdning=$tmp where id=$row[id]",__FILE__ . " linje " . __LINE__);
	}
	else { db_modify("insert into lagerstatus (vare_id, lager, beholdning) values ($vare_id, $lager, -$antal)",__FILE__ . " linje " . __LINE__);}
}
###############################################################

function krediter($id, $levdate, $beholdning, $vare_id, $antal, $pris, $linje_id, $serienr, $kred_linje_id) 
{
	global $sn_id;
	global $lev_nr;		
	global $fp;
	
	$rest=$antal;
	$sn_start=0;
	$kobsbelob=0;
	$a=0;
	$res_sum=0;

	$query = db_select("select posnr, kred_linje_id from ordrelinjer where id=$linje_id",__FILE__ . " linje " . __LINE__);
	$row =db_fetch_array($query); 
	$kred_linje_id=$row[kred_linje_id];
	$posnr=$row[posnr];

	if ($kred_linje_id>0) { #if Indsat 071106 grundet fejl ved negativt vareantal p�ordin� salgsordre.
		# Anvendes ved ved negativt vareantal p� ordin�r salgsordre - n�r varen tidligere har v�ret solgt til kunden
		$x=0;
		$q = db_select("select * from batch_salg where linje_id=$kred_linje_id",__FILE__ . " linje " . __LINE__);
		while ($r =db_fetch_array($q)) {
			$x++;
			$batch_kob_id[$x]=$r[batch_kob_id];
			$batch_kob_antal[$x]=$r[antal];
			if ($batch_kob_antal[$x]>$antal) $batch_kob_antal[$x]=$antal;
			if (!$batch_kob_id[$x]) {
				?>
					<script language="Javascript">
					<!--
					alert ("Der er observeret en uoverensstemmelse mellem mellem oprindelig ordre og denne (pos nr: <?php echo $posnr ?>)\nRapporter venligst til udviklingsteamet.  mail: fejl@saldi.dk")
					//-->
					</script>
				<?php
				print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
				exit;
			}
			$q2 = db_select("select rest from batch_kob where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			$r2 =db_fetch_array($q2);
			$kob_rest[$x]=$r2[rest]+$batch_kob_antal[$x];
			db_modify("update batch_kob set rest=$kob_rest[$x] where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			lagerstatus($vare_id, $lager, -$batch_kob_antal[$x]);	
			db_modify("insert into batch_salg(batch_kob_id, vare_id, linje_id, salgsdate, ordre_id, antal, lev_nr) values ($batch_kob_id[$x], $vare_id, $linje_id, '$levdate', $id, -$batch_kob_antal[$x], '$lev_nr')",__FILE__ . " linje " . __LINE__); # Rettet til $antal fra $batch_kob_antal[$x] -- rettet tilbage 12.11.07 dat det ikke fungerer hvis antal != batch_kob_antal[$x]  .
			$q3 = db_select("select id from batch_salg where batch_kob_id=$batch_kob_id[$x] and vare_id=$vare_id and linje_id=$linje_id and salgsdate='$levdate' and ordre_id=$id and antal=-$batch_kob_antal[$x] and lev_nr='$lev_nr' order by id desc",__FILE__ . " linje " . __LINE__); #se ovenfor.
			$r3 =db_fetch_array($q3);
			$batch_salg_id[$x]=$r3['id']; 
			if ($serienr) {
				$q4 = db_select("select * from serienr where salgslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
				while ($r4 =db_fetch_array($q4)) {
					db_modify("insert into serienr (kobslinje_id, vare_id, batch_kob_id, serienr, batch_salg_id, salgslinje_id) values ($r4[kobslinje_id], $r4[vare_id], $r4[batch_kob_id], '$r4[serienr]', $batch_salg_id[$x], $linje_id)",__FILE__ . " linje " . __LINE__); 
					db_modify("update serienr set batch_salg_id=-$r4[batch_salg_id] where id=$r4[id]",__FILE__ . " linje " . __LINE__);
				}
			}
		}
	} else {
		db_modify("update ordrelinjer set kred_linje_id = '-1' where id = $linje_id",__FILE__ . " linje " . __LINE__); #indsat 20071004
		db_modify("insert into batch_kob(vare_id, linje_id, kobsdate, ordre_id, antal, rest) values ($vare_id, $linje_id, '$levdate', $id, $antal, $antal)",__FILE__ . " linje " . __LINE__);
		if ($serienr) {
			$query = db_select("select * from serienr where salgslinje_id=-$kred_linje_id",__FILE__ . " linje " . __LINE__);
			while ($row =db_fetch_array($query)) {
				 db_modify("insert into serienr (kobslinje_id, vare_id, batch_kob_id, serienr, batch_salg_id, salgslinje_id) values ($row[kobslinje_id], $row[vare_id], $row[batch_kob_id], '$row[serienr]', $batch_salg_id, $linje_id)",__FILE__ . " linje " . __LINE__); 
				 db_modify("update serienr set batch_salg_id=-$row[batch_salg_id] where id=$row[id]",__FILE__ . " linje " . __LINE__);
			}
		}
	}
}

###############################################################
function batch ($linje_id) 
{
	$lager='';

	$leveres=0;
	$query = db_select("select * from ordrelinjer where id = '$linje_id'",__FILE__ . " linje " . __LINE__);
	if ($row = db_fetch_array($query)) {
		$antal=$row[antal];
		$leveres=$row[leveres];
		$posnr=$row[posnr];
		$vare_id=$row[vare_id];
		$varenr=$row['varenr'];
		$serienr=$row['serienr'];
		$query = db_select("select status, art, konto_id, ref from ordrer where id = '$row[ordre_id]'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$konto_id=$row[konto_id];
		$status=$row[status];
		$art=$row[art];

		if ($row= db_fetch_array(db_select("select afd from ansatte where navn = '$row[ref]'",__FILE__ . " linje " . __LINE__))) {
			if ($row= db_fetch_array(db_select("select kodenr from grupper where box1='$row[afd]' and art='LG'",__FILE__ . " linje " . __LINE__))) {$lager=$row['kodenr']*1;}
		}
	}

	$query = db_select("select * from batch_salg where linje_id = $linje_id",__FILE__ . " linje " . __LINE__);
	while($row = db_fetch_array($query)) $leveres=$antal-$row[antal];

	if (($antal>=0)&&($art!="DK")){	
		$x=0;
		$rest=array();
		$lev_rest=$leveres;
		if ($lager) $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 and lager = $lager order by kobsdate",__FILE__ . " linje " . __LINE__);
		else $query = db_select("select * from batch_kob where vare_id=$vare_id and rest > 0 order by kobsdate",__FILE__ . " linje " . __LINE__);
		while ($row = db_fetch_array($query)) {
			$x++;
			$batch_kob_id[$x]=$row['id'];
			$kobsdate[$x]=$row['kobsdate'];
			$rest[$x]=$row['rest'];
			$reserveret[$x]=0;
#			$pris[$x]=$row[pris];
			$q2 = db_select("select ordrenr from ordrer where id=$row[ordre_id]",__FILE__ . " linje " . __LINE__);
			$r2 = db_fetch_array($q2);
			$ordrenr[$x]=$r2[ordrenr];
			$q2 = db_select("select * from reservation where batch_kob_id=$row[id]",__FILE__ . " linje " . __LINE__);
			while ($r2 = db_fetch_array($q2)) {
				if ($r2['linje_id']!=$linje_id) {$reserveret[$x]=$reserveret[$x]+$r2['antal'];}
				else {
					$valg[$x]=$r2['antal'];
					$valgt.=$r2['antal'];
				} 
			}
			$k_ordreantal=$x;
			if (!$valgt) {
				if ($rest[$x]>=$lev_rest) {
					$valg[$x]=$lev_rest;
					$lev_rest=0;
				}	
				else {
					$valg[$x]=$rest[$x];
					$lev_rest=$lev_rest-$rest[$x];
				}
			}
		}
	$batch_antal=$x;
	} 
	if ($lev_rest==0) {
		 db_modify("delete from reservation where linje_id=$linje_id",__FILE__ . " linje " . __LINE__);
		 $temp=$linje_id*-1;
		 db_modify("delete from reservation where batch_salg_id=$temp",__FILE__ . " linje " . __LINE__);
		 for ($x=1; $x<=$batch_antal; $x++){
			 $lager=$lager*1;
			 if (($valg[$x]>0)&&(!$res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_kob_id, antal, lager) values ($linje_id, $vare_id, $batch_kob_id[$x], $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
			 elseif (($valg[$x]>0)&&($res_linje_id[$x])) {db_modify("insert into reservation (linje_id, vare_id, batch_salg_id, antal, lager) values ($res_linje_id[$x], $vare_id, $temp, $valg[$x], $lager)",__FILE__ . " linje " . __LINE__);}
		 } 
	}	
}
###############################################################
function samlevare($linje_id, $v_id, $leveres) 
{
	global $id;
	list($vare_id, $stk_antal, $antal) = fuld_stykliste($v_id, '', 'basisvarer');
	for ($x=1; $x<=$antal; $x++) {
		if ($r=db_fetch_array(db_select("select * from varer where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__))) {
			$stk_antal[$x]=$stk_antal[$x]*$leveres;
			db_modify("insert into ordrelinjer (ordre_id, varenr, vare_id, beskrivelse, antal, leveres, pris, samlevare, posnr) values ('$id', '$r[varenr]', '$vare_id[$x]', '$r[beskrivelse]', '$stk_antal[$x]', '$stk_antal[$x]', 0, $linje_id, '100' )",__FILE__ . " linje " . __LINE__);
		}
	}
}

function bogfor($id,$webservice) {	
	global $regnaar;
	global $fakturadate;
	global $valutakurs;
	global $pbs;
	global $mail_fakt;
	global $db;
	global $brugernavn;
	
	$query = db_select("select * from ordrer where id = $id",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);
	$ordredate=$row['ordredate'];
	$levdate=$row['levdate'];
	$fakturadate=$row['fakturadate'];
	$nextfakt=$row['nextfakt'];
	$art=$row['art'];
	$kred_ord_id=$row['kred_ord_id'];
	$valuta=$row['valuta'];
	$art=$row['art'];
	
	if ($row['status']!=2){
		return("invoice allready created for order id $id"); 
#		print "<BODY onLoad=\"javascript:alert('Fakturerering er allerede udf&oslash;rt')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#		exit;
	}
	
	$query = db_select("select box1, box2, box3, box4 from grupper where art='RA' and kodenr='$regnaar'",__FILE__ . " linje " . __LINE__);

	if ($row = db_fetch_array($query)){
	#		$year=substr(str_replace(" ","",$row['box2']),-2);#aendret 060308 - grundet mulighed for fakt i aar 2208
		$year=trim($row['box2']);
		$aarstart=str_replace(" ","",$year.$row['box1']);
	#		$year=substr(str_replace(" ","",$row['box4']),-2);
		$year=trim($row['box4']);
		$aarslut=str_replace(" ","",$year.$row['box3']);
	}
	$query = db_select("select * from ordrer where id = '$id'",__FILE__ . " linje " . __LINE__);
	$row = db_fetch_array($query);

	if (!$fakturadate){
		return("missing invoicedate for order $id"); 
		#	print "<meta http-equiv=\"refresh\" content=\"0;URL=fakturadato.php?id=$id&pbs=$pbs&mail_fakt=$mail_fakt&returside=bogfor.php\">";
		#exit;
	}

	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs as kurs, grupper.box3 as difkto from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$fakturadate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs']*1;
			$difkto=$r['difkto']*1;
			if (!db_fetch_array(db_select("select id from kontoplan where kontonr='$difkto' and regnskabsaar='$regnaar'",__FILE__ . " linje " . __LINE__))) {
				if ($webservice) return("Kontonr $difkto (kursdiff) eksisterer ikke");
				else {
					return("Kontonr $difkto (kursdiff) eksisterer ikke");
#					print "<BODY onLoad=\"javascript:alert('Kontonr $difkto (kursdiff) eksisterer ikke')\">";
#					print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#					exit;
				}
			}
		} else {
			$tmp = dkdato($fakturadate);
			return("Der er ikke nogen valutakurs for $valuta den $tmp (fakturadatoen).");
#			print "<BODY onLoad=\"javascript:alert('Der er ikke nogen valutakurs for $valuta den $tmp (fakturadatoen).')\">";
#			print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#			exit;
		}
	} else {
		$valuta='DKK';
		$valutakurs=100;
	}
	if (!$levdate){
		return ("Missing deliverydate");
#		print "<BODY onLoad=\"javascript:alert('Leveringsdato SKAL udfyldes')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#		exit;
	}	

	if ($levdate<$ordredate){
		return ("Deliverydate prior to orderdate");
#	 	print "<BODY onLoad=\"javascript:alert('Leveringsdato er f&oslash;r ordredato')\">";
#	 	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
# 	exit;
	}

	if ($fakturadate<$levdate)	{
		return ("Invoicedate prior to orderdate");
#		print "<BODY onLoad=\"javascript:alert('Fakturadato er f&oslash;r leveringsdato')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
#		exit;
	}	

	if (($nextfakt)&& ($nextfakt<=$fakturadate)){
		return ("Next_invoicedate prior to invoicedate");
#		print "<BODY onLoad=\"javascript:alert('Genfaktureringsdato skal v&aelig;re efter fakturadato')\">";
#		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
# 	exit;
	}
	list ($year, $month, $day) = explode('-', $fakturadate);
	$year=trim($year);
	$ym=$year.$month;

	if (!$webservice && ($ym<$aarstart || $ym>$aarslut))	{
		print "<BODY onLoad=\"javascript:alert('Fakturadato udenfor regnskabs&aring;r')\">";
		print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
		exit;
	}
	if ($valuta && $valuta!='DKK') {
		if ($r= db_fetch_array(db_select("select valuta.kurs from valuta, grupper where grupper.art='VK' and grupper.box1='$valuta' and valuta.gruppe=".nr_cast("grupper.kodenr")." and valuta.valdate <= '$ordredate' order by valuta.valdate desc",__FILE__ . " linje " . __LINE__))) {
			$valutakurs=$r['kurs'];
		} else {
			$tmp = dkdato($ordredate);
			return("Der er ikke nogen valutakurs for $valuta den $ordredate (ordredatoen)");
		}
	}
	if (!$fejl) {
 		transaktion("begin");
		$fakturanr=1;
		$query = db_select("select fakturanr from ordrer where art = 'DO' or art = 'DK'",__FILE__ . " linje " . __LINE__);

		while ($row = db_fetch_array($query)){
			if ($fakturanr <= $row[fakturanr]) {$fakturanr = $row[fakturanr]+1;}
		}

		if ($fakturanr == 1) {
			$query = db_select("select box1 from grupper where art = 'RB' order by kodenr",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)){$fakturanr=$row[box1]*1;}
		}

		if ($fakturanr < 1) $fakturanr = 1;	
		batch_kob($id, $art); 
		batch_salg($id);
		db_modify("update ordrer set status=3, fakturanr=$fakturanr, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
		$r = db_fetch_array(db_select("select box5 from grupper where art='DIV' and kodenr='3'",__FILE__ . " linje " . __LINE__));

		$svar=momsupdat($id);
		if ($r['box5']=='on') $svar=bogfor_nu($id,$webservice);
		if ($svar != "OK") {
			return($svar);
			exit;
		} else transaktion("commit");
	}
	return($svar);
} #endfunc bogfor	

function momsupdat($id) {
	global $db;
	global $brugernavn;
	
	$r=db_fetch_array(db_select("select momssats from ordrer where id = $id",__FILE__ . " linje " . __LINE__));
	$momssats=$r['momssats']*1;
	$q=db_select("select * from ordrelinjer where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($r=db_fetch_array($q)) {
		$sum=$sum+afrund(($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal'],2);
		if ($r['vare_id'] && $r['momsfri']!='on')
			$moms=$moms+afrund(($r['pris']-($r['pris']/100*$r['rabat']))*$r['antal']/100*$momssats,2);
#
#		echo "$sum<br>"; 	
	}
#	$moms=afrund($sum/100*$momssats,2);
#echo "$moms<br>";	
	db_modify("update ordrer set sum=$sum, moms=$moms where id = '$id'",__FILE__ . " linje " . __LINE__);
	return("OK");
}

function batch_salg($id) {
	global $fakturadate; 
	global $valutakurs;
	
	$x=0;
	$query = db_select("select * from batch_salg where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$x++;
		$batch_id[$x]=$row['id'];
		$vare_id[$x]=$row['vare_id'];	
		$antal[$x]=$row['antal'];
		$serienr[$x]=$row['serienr'];
		$batch_kob_id[$x]=$row['batch_kob_id'];
		$batch_linje_id[$x]=$row['linje_id'];
	}
	$linjeantal=$x;	
	

	for ($x=1; $x<=$linjeantal; $x++) {
		$kostpris=0;

		$query = db_select("select id, pris, rabat, projekt from ordrelinjer where id = $batch_linje_id[$x]",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$ordre_linje_id=$row['id'];
		$pris = $row['pris']-($row['pris']*$row['rabat']/100);
		$projekt=$row['projekt']*1;
		if ($valutakurs) $pris=afrund($pris*$valutakurs/100,3);
		db_modify("update batch_salg set pris=$pris, fakturadate='$fakturadate' where id=$batch_id[$x]",__FILE__ . " linje " . __LINE__); 
		if ($batch_kob_id[$x]) {
			$query = db_select("select pris, ordre_id from batch_kob where id = $batch_kob_id[$x]",__FILE__ . " linje " . __LINE__);
			if ($row = db_fetch_array($query)) {
				$kostpris=$row['pris'];
				if ($row['ordre_id']) {
					$query = db_select("select status from ordrer where id = $row[ordre_id]",__FILE__ . " linje " . __LINE__);
					$row = db_fetch_array($query);
					if ($row['status']){$kobsstatus=$row['status'];}
				}	
				else {$kobsstatus=0;}
			}
		}
#		else {#if ($batch_kob_id[$x]) 
	
		$query2 = db_select("select gruppe from varer where id = $vare_id[$x]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$box1=trim($row2['box1']); $box2=trim($row2['box2']); $box3=trim($row2['box3']); $box4=trim($row2['box4']); $box8=trim($row2['box8']); $box9=trim($row2['box9']);
		db_modify("update ordrelinjer set bogf_konto=$box4, projekt=$projekt where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
		if ($box9=='on'){ # box 9 betyder at der anvendes batch styring  
			if (!$batch_kob_id[$x]) { # saa er varen ikke paa lager, dvs at indkobsordren skal findes i tabellen reservation
				$query = db_select("select linje_id, lager from reservation where batch_salg_id = $batch_id[$x]",__FILE__ . " linje " . __LINE__);
				$row = db_fetch_array($query);
				$res_antal=$res_antal+$row['antal']; 
				$res_linje_id=$row['linje_id'];
				$lager=$row['lager'];
				$r1 = db_fetch_array(db_select("select ordre_id, pris, rabat, projekt from ordrelinjer where id = $res_linje_id",__FILE__ . " linje " . __LINE__)); 
				$kob_ordre_id = $r1['ordre_id'];
				$projekt = $r1['projekt'];
				$r2 = db_fetch_array(db_select("select valutakurs from ordrer where id = $kob_ordre_id",__FILE__ . " linje " . __LINE__));
				$kostpris = ($r1['pris']-($r1['pris']*$r1['rabat']/100))*$r2['valutakurs']/100;
				db_modify("update ordrelinjer set kostpris = $kostpris where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
			# Hvis levering er sket i flere omgange vil der vaere flere batch_salg linjer paa samme kobs linje, derfor nedenstaende.	 
				if ($row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id",__FILE__ . " linje " . __LINE__))) {
					$batch_kob_id[$x]=$row['id'];
				}
				else {
					db_modify("insert into batch_kob (linje_id, vare_id, ordre_id, pris, lager) values ($res_linje_id, $vare_id[$x], $kob_ordre_id, $pris, $lager)",__FILE__ . " linje " . __LINE__); #Antal indsaettes ikke - dette styres i "reservation"
					$row = db_fetch_array(db_select("select id from batch_kob where linje_id=$res_linje_id and vare_id=$vare_id[$x] and ordre_id=$kob_ordre_id",__FILE__ . " linje " . __LINE__));
					$batch_kob_id[$x]=$row['id'];
				} 
				db_modify("update reservation set batch_kob_id=$batch_kob_id[$x] where linje_id = $res_linje_id",__FILE__ . " linje " . __LINE__);
				db_modify("update batch_salg set batch_kob_id=$batch_kob_id[$x] where id=$batch_id[$x]",__FILE__ . " linje " . __LINE__);		
			}
			$row = db_fetch_array(db_select("select pris from batch_kob where id=$batch_kob_id[$x]",__FILE__ . " linje " . __LINE__)); # kostprisen findes..
			if ($row['pris']) $pris=$row['pris']; 
			if ($box1&&$box2) { #kostvaerdien flyttes fra "afgang varelager" til "varekob".- hvis der ikke bogfoeres direkte paa varekobs kontoen
				#	if ($valutakurs) $pris=$pris*100/$valutakurs;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$pris', 0, $id, $box2,'$projekt')",__FILE__ . " linje " . __LINE__);
				$pris=$pris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$pris', 0, $id, $box3,'$projekt')",__FILE__ . " linje " . __LINE__);
			}
		} elseif ($box8=='on') { # hvis box8 er 'on' er varen lagerfoert
			$row = db_fetch_array(db_select("select kostpris from varer where id=$vare_id[$x]",__FILE__ . " linje " . __LINE__));
			if (!$row['kostpris']) $kostpris='0';
			else $kostpris=$row['kostpris'];
#			if ($valutakurs) $kostpris=$kostpris*100/$valutakurs;
			db_modify("update ordrelinjer set kostpris = $kostpris where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
			if ($box1&&$box2) {
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$kostpris', 0, $id, $box2,'$projekt')",__FILE__ . " linje " . __LINE__);
				$kostpris=$kostpris*-1;
				db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto, projekt) values ('-1',$antal[$x], '$kostpris', 0, $id, $box3,'$projekt')",__FILE__ . " linje " . __LINE__);
			}
		}
	}
}
####### batch_kob anvendes hvis der krediteres en vare som ikke er blevet solgt - og derfor betragtes som et varekoeb ####### 
function batch_kob($id, $art) 
{
	global $fakturadate; 
	global $valutakurs;
	
	$query = db_select("select * from batch_kob where ordre_id = '$id'",__FILE__ . " linje " . __LINE__);
	while ($row = db_fetch_array($query)){
		$x++;
		$batch_id=$row['id'];
		$vare_id=$row['vare_id'];
		$antal=$row['antal'];
		$projekt=$row['projekt']*1;
		$serienr=$row['serienr'];
		$batch_kob_id=$row['batch_kob_id']; 
		$query2 = db_select("select id, pris, rabat, projekt from ordrelinjer where id = $row[linje_id]",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$ordre_linje_id=$row2['id'];
		$pris = $row2[pris]-($row2['pris']*$row2['rabat']/100);
		if ($row['pris']) {$diff = $pris-$row['pris'];}
		db_modify("update batch_kob set pris=$pris, fakturadate='$fakturadate' where id=$batch_id",__FILE__ . " linje " . __LINE__);
 		$query2 = db_select("select gruppe from varer where id = $vare_id",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$gruppe=$row2['gruppe'];
		$query2 = db_select("select * from grupper where art='VG' and kodenr='$gruppe'",__FILE__ . " linje " . __LINE__);
		$row2 = db_fetch_array($query2);
		$box1=trim($row2['box1']); $box2=trim($row2['box2']); $box3=trim($row2['box3']); $box4=trim($row2['box4']); $box8=trim($row2['box8']); $box9=trim($row2['box9']);
		db_modify("update ordrelinjer set bogf_konto=$box4 where id=$ordre_linje_id",__FILE__ . " linje " . __LINE__);
		if ($box9=='on'){
			$pris=$pris-$diff;
			if (!$pris){$pris=0;}
			if ($valutakurs) $pris=$pris*100/$valutakurs;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', $antal, $pris, 0, $id, $box3,'$projekt')",__FILE__ . " linje " . __LINE__);
			$pris=$pris*-1;
			db_modify("insert into ordrelinjer (posnr, antal, pris, rabat, ordre_id, bogf_konto,projekt) values ('-1', $antal, $pris, 0, $id, $box2,'$projekt')",__FILE__ . " linje " . __LINE__);
		}
	}
}
function bogfor_nu($id,$webservice)
{
	include("../includes/genberegn.php");
	include("../includes/forfaldsdag.php");
	global $db;
	global $regnaar;
	global $valuta;
	global $valutakurs;
	global $difkto;
	global $title;
#	print "<table><tbody>";
	$svar="OK";	


	$d_kontrol=0; 
	$k_kontrol=0;
	$logdate=date("Y-m-d");
	$logtime=date("H:i");
	$q = db_select("select box1, box2, box3, box4, box5 from grupper where art='RB'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		if (trim($r['box3'])=="on") $faktbill=1; 
		else {$faktbill=0;}
		if (trim($r['box4'])=="on") $modtbill=1; 
		else $modtbill=0;
		if (trim($r['box5'])=="on") {
			$no_faktbill=1;
			$faktbill=0;
		}	 
		else $no_faktbill=0;
	}
	$projekt=array();
	$x=0;
	$q = db_select("select * from ordrer where id='$id'",__FILE__ . " linje " . __LINE__);
	if ($r = db_fetch_array($q)) {
		$art=$r['art'];
		$konto_id=$r['konto_id'];
		$kontonr=str_replace(" ","",$r['kontonr']);
		$firmanavn=trim($r['firmanavn']);
		$modtagelse=$r['modtagelse'];
		$transdate=($r['fakturadate']);
		$fakturanr=$r['fakturanr'];
		$ordrenr=$r['ordrenr'];
#echo "$firmanavn | $ordrenr<br>";		
		$valuta=$r['valuta'];
		$kred_ord_id=$r['kred_ord_id'];
		if (!$valuta) $valuta='DKK';
		$projekt[0]=$r['projekt']*1;
		$betalingsbet=$r['betalingsbet'];
		$betalingsdage=$r['betalingsdage']*1;
#		$refnr;
		$moms=$r['moms']*1;
#		else {$moms=afrund($r['sum']*$r['momssats']/100,2);}
		$sum=$r['sum']+$moms;
#echo "sum $r[sum] + $moms = $sum<br>";
#exit;		
		$ordreantal=$x;
		$forfaldsdate=usdate(forfaldsdag($r['fakturadate'], $betalingsbet, $betalingsdage));
		$r2= db_fetch_array(db_select("select id, afd from ansatte where navn = '$r[ref]'",__FILE__ . " linje " . __LINE__));
		$afd=$r2['afd']*1;#sikkerhed for at 'afd' har en vaerdi 
		$ansat=$r2['id']*1;
		if ($no_faktbill==1) $bilag='0';
		else $bilag=trim($fakturanr);
		$udlign=0;
		if (substr($art,1,1)=='K') {
			$beskrivelse ="Kreditnota - ".$fakturanr;
			$r=db_fetch_array(db_select("select fakturanr,fakturadate from ordrer where id='$kred_ord_id'",__FILE__ . " linje " . __LINE__));
			$tmp=$sum*-1;
			if (db_fetch_array(db_select("select * from openpost  where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]' and udlignet != '1'",__FILE__ . " linje " . __LINE__))) {
				db_modify("update openpost set udlignet = 1 where konto_id='$konto_id' and amount='$tmp' and faktnr='$r[fakturanr]' and transdate='$r[fakturadate]'");		
				$udlign=1;
			}
		} elseif ($art=='PO') $beskrivelse ="Bon - ".$fakturanr;
	 	else $beskrivelse ="Faktura - ".$fakturanr;
		if ($art!='PO') {
			db_modify("insert into openpost (konto_id, konto_nr, faktnr, amount, beskrivelse, udlignet, transdate, kladde_id, refnr, valuta, valutakurs, forfaldsdate) values ('$konto_id', '$kontonr', '$fakturanr', '$sum', '$beskrivelse', '$udlign', '$transdate', '$udlign', '$id', '$valuta', '$valutakurs','$forfaldsdate')",__FILE__ . " linje " . __LINE__);
			$r = db_fetch_array(db_select("select gruppe from adresser where id='$konto_id'",__FILE__ . " linje " . __LINE__));
			$r = db_fetch_array(db_select("select beskrivelse, box2 from grupper where art = 'DG' and kodenr='$r[gruppe]'",__FILE__ . " linje " . __LINE__));
			$kontonr=$r['box2']; # Kontonr aendres fra at vaere leverandoerkontonr til finanskontonr
			$tekst="Kontonummer for Debitorgruppe `$r[beskrivelse]` er ikke gyldigt";
			if (!$kontonr && $webservice) return($tekst);
			elseif(!$kontonr) print "<BODY onLoad=\"javascript:alert('$tekst')\">";
		} else $kontonr="58200"; # midleritdig kun til brug med POS
		if ($sum>0) {$debet=$sum; $kredit='0';}
		else {$debet='0'; $kredit=$sum*-1;}
		if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.		
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		$debet=afrund($debet,2);
		$kredit=afrund($kredit,2);
#echo "A insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
		db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$kontonr', '$fakturanr', '$debet', '$kredit', '0', $afd, '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
		if ($valutakurs) $maxdif=2; #Der tillades 2 oeres afrundingsdiff 
		$p=0;
		$q = db_select("select distinct(projekt) from ordrelinjer where ordre_id=$id and vare_id >	'0'",__FILE__ . " linje " . __LINE__);
		while ($r = db_fetch_array($q)) {
			$p++;
			$projekt[$p]=$r['projekt']*1;
		}
		$projektantal=$p;
		for ($t=1;$t<=2;$t++)	{
			for ($p=1;$p<=$projektantal;$p++) {	
				$y=0;
				$tjek= array();
				$bogf_konto = array();
				if ($t==1) {
#echo "select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr>=0<br>";					
					$q = db_select("select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr>=0",__FILE__ . " linje " . __LINE__);
				} else {
#echo "select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr<0<br>";					
					$q = db_select("select * from ordrelinjer where ordre_id='$id' and projekt='$projekt[$p]' and posnr<0",__FILE__ . " linje " . __LINE__);
				}
				while ($r = db_fetch_array($q)) {
					if ($valutakurs) $maxdif=$maxdif+2; #Og yderligere 2 pr ordrelinje.
					$tmp=$projekt[$p].":".$r['bogf_konto'];
					if (!in_array($r['bogf_konto'], $bogf_konto)) {
						$y++;
						$bogf_konto[$y]=$r['bogf_konto'];
						$pris[$y]=$r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100);
						$pris[$y]=afrund($pris[$y],3); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
					}	else {
						for ($a=1; $a<=$y; $a++) {
							if ($bogf_konto[$a]==$r['bogf_konto']) {
								$pris[$a]=$pris[$a]+($r['pris']*$r['antal']-($r['pris']*$r['antal']*$r['rabat']/100));
								$pris[$a]=afrund($pris[$a],3); #Afrunding tilfoejet 2009.01.26 grundet diff i ordre 98 i saldi_104
							}
						}		 
					}
				}
				$ordrelinjer=$y;
#echo "ol $ordrelinjer<br>";				
				for ($y=1;$y<=$ordrelinjer;$y++) {
					if ($bogf_konto[$y]) {
						if ($pris[$y]>0) {$kredit=$pris[$y];$debet=0;}
						else {$kredit=0; $debet=$pris[$y]*-1;}
						if ($t==1 && $valutakurs) {$kredit=$kredit*$valutakurs/100;$debet=$debet*$valutakurs/100;} # Omregning til DKR.		
						$kredit=afrund($kredit,3);$debet=afrund($debet,3);
						$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
						$debet=afrund($debet,2);
						$kredit=afrund($kredit,2);
#echo "B insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '$id')<br>";						
						db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$bogf_konto[$y]', '$fakturanr', '$debet', '$kredit', '0','$afd', '$logdate', '$logtime', '$projekt[$p]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
					}
				}
			}
		}
		$query = db_select("select gruppe from adresser where id='$konto_id';",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$query = db_select("select box1 from grupper where art='DG' and kodenr='$row[gruppe]';",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$box1=substr(trim($row[box1]),1,1);
		$query = db_select("select box1 from grupper where art='SM' and kodenr='$box1'",__FILE__ . " linje " . __LINE__);
		$row = db_fetch_array($query);
		$box1=trim($row['box1']);
		if ($moms > 0) {$kredit=$moms; $debet='0';}
		else {$kredit='0'; $debet=$moms*-1;} 
		if ($valutakurs) {$kredit=afrund($kredit*$valutakurs/100,3);$debet=afrund($debet*$valutakurs/100,3);} # Omregning til DKR.		
		$kredit=afrund($kredit,3);$debet=afrund($debet,3);
		$d_kontrol=$d_kontrol+$debet; $k_kontrol=$k_kontrol+$kredit;
		$diff=afrund($d_kontrol-$k_kontrol,3);
		$absdiff=abs($diff);
		if ($moms && $valutakurs && $valutakurs!=100 && $absdiff>=0.01 && $absdiff<=0.05) {
			if ($debet > 0) {
				$debet=$debet+$diff;
				$d_kontrol=$d_kontrol+$diff;
			} elseif ($kredit > 0) {
				$kredit=$kredit+$diff;
				$k_kontrol=$k_kontrol+$diff;
			}	
		} 
#echo "moms $moms<br>";	
		$moms=afrund($moms,2);
#echo "C insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";		
		if ($moms) db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$box1', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
		$valutakurs=$valutakurs*1;
#echo "update ordrer set status=4, valutakurs=$valutakurs where id=$id<br>";		
		db_modify("update ordrer set status=4, valutakurs=$valutakurs where id=$id",__FILE__ . " linje " . __LINE__);
		db_modify("delete from ordrelinjer where ordre_id=$id and posnr < 0",__FILE__ . " linje " . __LINE__);
	}
	$d_kontrol=afrund($d_kontrol,2);
	$k_kontrol=afrund($k_kontrol,2);
#echo "$d_kontrol $k_kontrol<br>";	
	if ($diff=afrund($d_kontrol-$k_kontrol)) {
			if ($valuta!='DKK' && abs($diff)<=$maxdif) { #Der maa max vaere en afvigelse paa 1 oere pr ordrelinje m fremmed valuta;
			$debet=0; $kredit=0;
			if ($diff<0) $debet=$diff*-1;
			else $kredit=$diff;
			$debet=afrund($debet,2);
			$kredit=afrund($kredit,2);
#echo "D insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$difkto', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')<br>";
			db_modify("insert into transaktioner (bilag, transdate, beskrivelse, kontonr, faktura, debet, kredit, kladde_id, afd, logdate, logtime, projekt, ansat, ordre_id) values ('0', '$transdate', '$beskrivelse', '$difkto', '$fakturanr', '$debet', '$kredit', '0', '$afd', '$logdate', '$logtime', '$projekt[0]', '$ansat', '$id')",__FILE__ . " linje " . __LINE__);
		} else {
# echo "Id	$id<br>";
# echo "D	$d_kontrol K $k_kontrol<br>";	
			$message=$db." | Uoverensstemmelse i posteringssum: ordre_id=$id, d=$d_kontrol, k=$k_kontrol | ".__FILE__ . " linje " . __LINE__." | ".$brugernavn." ".date("Y-m-d H:i:s");
			$headers = 'From: fejl@saldi.dk'."\r\n".'Reply-To: fejl@saldi.dk'."\r\n".'X-Mailer: PHP/' . phpversion();
			mail('fejl@saldi.dk', 'SALDI Fejl', $message, $headers);
			if (!$webservice) print "<BODY onLoad=\"javascript:alert('Der er konstateret en uoverensstemmelse i posteringssummen, ordre $ordrenr, kontakt DANOSOFT p&aring; telefon 4690 2208')\">";
			else return("Der er konstateret en uoverensstemmelse i posteringssummen, ordre $ordrenr, kontakt DANOSOFT p&aring; telefon 4690 2208' debet $debet != kredit $kredit");
#     	print "<meta http-equiv=\"refresh\" content=\"0;URL=ordre.php?id=$id\">";
			exit;
		}
	} 
	if ($title != "Massefakturering" && !$webservice) genberegn($regnaar);
	return($svar);
}

?>
