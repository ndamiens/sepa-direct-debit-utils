<?php

// usage : php sepa_split.php file.xml nb_split

$doc = new DOMDocument();
$doc->load($argv[1]);

$dests = [];

function docVide() {
    $doc = new DOMDocument();
    $doc->load("vide.xml");
    return $doc;
}

for ($i = 0; $i < (int) $argv[2]; $i++) {
    $dests[] = docVide();
}

$checksums = [];

$inserts = [];

function insert(DOMDocument $doc, DOMNode $pointOfInsert, DOMNode $paiement) {
    $paiementCopy = $doc->importNode($paiement, true);
    $pointOfInsert->appendChild($paiementCopy);
}

foreach ($dests as $dest) {
    $inserts[] = getPointOfInsertion($dest);
    $checksums[] = ["montant" => 0, "n" => 0];
}

$paiements = $doc->getElementsByTagName("DrctDbtTxInf");
$n = $paiements->count();
echo "$n paiements\n";

$doc_count = count($dests);
for ($i = 0; $i < $n; $i++) {
    echo "$i\r";
    flush();
//    echo " {$checksums[$i % $doc_count]["montant"]} += ".getMontant($paiements->item($i))." \n";
    $checksums[$i % $doc_count]["montant"] = bcadd($checksums[$i % $doc_count]["montant"], getMontant($paiements->item($i)),2);
    $checksums[$i % $doc_count]["n"] += 1;
    insert($dests[$i % $doc_count], $inserts[$i % $doc_count], $paiements->item($i));
}

print_r($checksums);

for ($i = 0; $i < $doc_count; $i++) {
    setStats($dests[$i], $checksums[$i]);
    flushDoc($dests[$i], $i + 1, $doc_count);
}

/**
 * l'endroit ou on doit ajouter les paiements
 * @param DOMDocument $doc
 * @return DOMNode
 */
function getPointOfInsertion(DOMDocument $doc) {
    return $doc->getElementsByTagName("PmtInf")[0];
}

function getMontant(DOMElement $node) {
    $montant = $node->getElementsByTagName("InstdAmt")->item(0)->nodeValue;
    return $montant; //(int) ($montant * 1000);
}

function setStats(DOMDocument $doc, $stats) {
    $tagsCtrlSum = $doc->getElementsByTagName("CtrlSum");
    for ($i = 0; $i < $tagsCtrlSum->count(); $i++) {
        $tagsCtrlSum->item($i)->nodeValue = $stats['montant']; //sprintf("%0.2d",$stats['montant']/1000);
    }
    $tagsNbOfTxs = $doc->getElementsByTagName("NbOfTxs");
    for ($i = 0; $i < $tagsNbOfTxs->count(); $i++) {
        $tagsNbOfTxs->item($i)->nodeValue = $stats['n'];
    }
}

function flushDoc(DOMDocument $doc, $doc_n, $doc_count) {
    $tagsMsgId = $doc->getElementsByTagName("MsgId");
    for ($i = 0; $i < $tagsMsgId->count(); $i++) {
        $tagsMsgId->item($i)->nodeValue .= "-{$doc_n}";
    }
    $doc->formatOutput = true;
    $doc->save("gen2_split_{$doc_n}_{$doc_count}.xml");
}
