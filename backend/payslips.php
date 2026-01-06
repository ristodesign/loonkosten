<?php
// backend/sepa.php - Volledige SEPA Credit Transfer XML-generatie (pain.001.001.03)
// Compatibel met Nederland en België
require_once __DIR__ . '/../config.php';
require_once 'api_db.php';

function generate_sepa_xml($payments, $bedrijf, $periode) {
	if (empty($payments)) {
		return null;
	}

	// Unieke identifiers
	$msg_id = 'LOON' . date('YmdHis') . rand(100, 999);
	$credt_tm = date('Y-m-d\TH:i:s');
	$pmt_inf_id = 'LOON' . date('Ymd');
	$reqd_exctn_dt = date('Y-m-d', strtotime($periode['eind_datum'] . ' + 5 days')); // 5 dagen na periode

	$total_amount = array_sum(array_column($payments, 'amount'));
	$nb_of_txs = count($payments);

	// Begin XML
	$xml = '<?xml version="1.0" encoding="UTF-8"?>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.001.001.03">
  <CstmrCdtTrfInitn>
	<GrpHdr>
	  <MsgId>' . $msg_id . '</MsgId>
	  <CreDtTm>' . $credt_tm . '</CreDtTm>
	  <NbOfTxs>' . $nb_of_txs . '</NbOfTxs>
	  <CtrlSum>' . number_format($total_amount, 2, '.', '') . '</CtrlSum>
	  <InitgPty>
		<Nm>' . htmlspecialchars($bedrijf['naam']) . '</Nm>
	  </InitgPty>
	</GrpHdr>
	<PmtInf>
	  <PmtInfId>' . $pmt_inf_id . '</PmtInfId>
	  <PmtMtd>TRF</PmtMtd>
	  <NbOfTxs>' . $nb_of_txs . '</NbOfTxs>
	  <CtrlSum>' . number_format($total_amount, 2, '.', '') . '</CtrlSum>
	  <ReqdExctnDt>' . $reqd_exctn_dt . '</ReqdExctnDt>
	  <Dbtr>
		<Nm>' . htmlspecialchars($bedrijf['naam']) . '</Nm>
	  </Dbtr>
	  <DbtrAcct>
		<Id>
		  <IBAN>' . htmlspecialchars($bedrijf['iban_bedrijf'] ?? 'NL91ABNA0417164300') . '</IBAN>
		</Id>
	  </DbtrAcct>
	  <DbtrAgt>
		<FinInstnId>
		  <BIC>' . htmlspecialchars($bedrijf['bic_bedrijf'] ?? 'ABNANL2A') . '</BIC>
		</FinInstnId>
	  </DbtrAgt>
	  <ChrgBr>SLEV</ChrgBr>';

	foreach ($payments as $index => $p) {
		$end_to_end_id = $msg_id . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
		$xml .= '
	  <CdtTrfTxInf>
		<PmtId>
		  <EndToEndId>' . $end_to_end_id . '</EndToEndId>
		</PmtId>
		<Amt>
		  <InstdAmt>' . number_format($p['amount'], 2, '.', '') . '</InstdAmt>
		</Amt>
		<Cdtr>
		  <Nm>' . htmlspecialchars($p['name']) . '</Nm>
		</Cdtr>
		<CdtrAcct>
		  <Id>
			<IBAN>' . htmlspecialchars($p['iban']) . '</IBAN>
		  </Id>
		</CdtrAcct>';
		if (!empty($p['bic'])) {
			$xml .= '
		<CdtrAgt>
		  <FinInstnId>
			<BIC>' . htmlspecialchars($p['bic']) . '</BIC>
		  </FinInstnId>
		</CdtrAgt>';
		}
		$xml .= '
		<RmtInf>
		  <Ustrd>' . htmlspecialchars($p['description']) . '</Ustrd>
		</RmtInf>
	  </CdtTrfTxInf>';
	}

	$xml .= '
	</PmtInf>
  </CstmrCdtTrfInitn>
</Document>';

	// Bestand opslaan
	$filename = "salaris_" . date('Ym', strtotime($periode['start_datum'])) . "_" . $msg_id . ".xml";
	$full_path = SEPA_DIR . $filename;

	if (!is_dir(SEPA_DIR)) {
		mkdir(SEPA_DIR, 0777, true);
	}

	file_put_contents($full_path, $xml);

	return $filename; // Relatief pad voor download
}

// AJAX call (optioneel)
if (isset($_POST['generate_sepa'])) {
	$payments = json_decode($_POST['payments'], true);
	$bedrijf = json_decode($_POST['bedrijf'], true);
	$periode = json_decode($_POST['periode'], true);

	$sepa_path = generate_sepa_xml($payments, $bedrijf, $periode);

	json_response(['sepa_path' => $sepa_path], true, 'SEPA-bestand gegenereerd');
}
?>