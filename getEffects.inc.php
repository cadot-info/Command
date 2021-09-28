<?php
$class = 'App\Entity\\' . $this->Entity;
$r = new \ReflectionClass(new $class); //property of class
//array of search
$aSupprimer = array('/**', '*/'); // for cleaning
$mCrud = array('pour éviter retour false', 'ATTR', 'PARTIE', 'EXTEND', 'OPT', 'TPL', 'TWIG', 'ALIAS', 'RELATION', 'COLLECTION'); // array for create
$FUnique = array('EXTEND', 'PARTIE', 'ALIAS', 'RELATION', 'COLLECTION'); //array with a uniq value
foreach ($r->getProperties() as $property) {
    $name = $property->getName();
    $docs = (explode("\n", $property->getDocComment()));
    //list comment tags
    foreach ($docs as $doc) {
        //remove tag
        if (!in_array(trim($doc), $aSupprimer)) {
            //remove spaces
            $docClean = trim($doc);
            if (substr($docClean, 0, strlen('* '))) {
                $docClean = trim(substr($docClean, strlen('* ')));
            }
            //list tag with =
            $posEgale = strpos($docClean, '=');
            if ($posEgale !== false) {
                if ($type = array_search(substr($docClean, 0, $posEgale), $mCrud)) {
                    //if it's a only value  field
                    if (in_array($mCrud[$type], $FUnique) !== false) {
                        $res[$name][$mCrud[$type]] = substr($docClean, $posEgale + 1);
                    } else { //else if can set multiple value
                        $res[$name][$mCrud[$type]][] = substr($docClean, $posEgale + 1);
                    }
                } else { //if he has = but it's not a word of crudmick
                    $res[$name]['AUTRE'][] = $docClean;
                }
            } else {
                //for others
                $res[$name]['AUTRE'][] = $docClean;
            }
        }
    }
}
//minimum options for crudmick
if (!isset($this->res['id']['EXTEND'])) {
    //see in .env
    if (!isset($_ENV['EXTEND'])) {
        echo ('Please get a EXTEND for id (example: EXTEND=admin/admin.html.twig, used bu twigs)');
        exit();
    } else
        $this->extend = $_ENV['EXTEND'];
} else {
    $this->extend = $this->res['id']['EXTEND'];
}
//SORTABLE
$this->sortable = false;
if (isset($res['id']['ATTR'])) if ($this->searchInValue($res['id']['ATTR'], 'sortable') !== false) $this->sortable = true;
if ($_ENV['SORTABLE'] == "1") $this->sortable = true;
$this->res = $res;
