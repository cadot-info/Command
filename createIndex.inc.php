<?php
$res = $this->res;
$Entity = $this->Entity;
$entity = strToLower($Entity);
$timestamptable = $this->timestamptable;
$html = $this->twigParser(file_get_contents($this->path . 'index.html.twig'), array('entity' => $entity, 'Entity' =>
$Entity, 'extends' => $this->extend));
//actions show or hide
$actions = ['no_action_show', 'no_action_clone', 'no_action_delete', 'no_action_edit', 'no_action_new'];
foreach ($actions as $action) {
    if ($this->searchInValue($res['id']['AUTRE'], $action))
        $html = $this->twigParser($html, array($action => 'style="display:none;"'));
    else
        $html = $this->twigParser($html, array($action => ''));
}
//code for sortable ATTR
$cursorSortable = ''; //style of cursor for sortable
if ($this->sortable) {
    $html = $this->twigParser($html, ['sortable' => '{% set list=findOneBy("sortable",{"entite":"' . $Entity . '"}) %}' . "\n" . '{% if list != null %}<input type="hidden" id="ex_sortable" value="{{list.Ordre}}">' . "\n" . '{% endif %}<input entite="' . $Entity . '" id="save_sortable" type="hidden">']);
    //$cursorSortable = 'style="cursor:move;"';
} else {
    $html = \str_replace('¤sortable¤', '', $html);
    $html = \str_replace('class="sortable"', '', $html);
}

//loop on fields for create header of table
$entete = ''; //content head of table
foreach ($res as $field => $val) {
    $no_index = false;
    //verify show for index
    if (isset($val['ATTR'])) $no_index = in_array('no_index', $val['ATTR']) ? true : false;
    //if no_index jump this field
    if (!$no_index) {
        $entete .= "<th>";
        //label or name for text field
        $finentete = ucfirst($field);
        if (isset($val['OPT'])) {
            if ($tete = $this->searchInValue($val['OPT'], 'label')) {
                $finentete = $tete;
            }
        }
        $entete .= $finentete . "</th>\n";
    }
}
//parse index.html with headers
$html = $this->twigParser($html, ['entete' => $entete]);

//loop on fields for create row of table
$finalrow = '';
foreach ($res as $field => $val) {
    $row = ''; //content temp row
    $filters = ''; //content the TWIG filters
    $no_index = false;
    //verify show for index
    if (isset($val['ATTR'])) $no_index = in_array('no_index', $val['ATTR']) ? true : false;
    //if no_index jump this field
    if (!$no_index) {
        $Field = ucfirst($field);
        //create TWIG filters
        if (isset($val['TWIG'])) foreach ($val['TWIG'] as $twig) $filters .= "|" . $twig;

        //if it's a relation field
        if ($this->is_relation($val['AUTRE']) !== false) {
            $row .= "\n{% for item in $Entity.$field %}{{ item }},{% endfor %}";
        }

        //if it's a choice
        if (isset($val['OPT']))
            if ($valChoices = $this->searchInValue($val['OPT'], 'choices')) {
                //choices to array
                $choices = json_decode($valChoices);
                //string for content res
                $row .= "{{ $Entity.$field|json_encode }}";
            }
        //if it's ALIAS
        if (isset($val['ALIAS'])) {
            // uploadjs has ATTR text or picture or nothing
            if ($val['ALIAS'] == 'uploadjs' || $val['ALIAS'] == 'collection') {
                //get the type by ATTR with default text
                if (isset($val['ATTR'])) {
                    $attr = explode('=>', $val['ATTR'][0]);
                    $type = $attr[0];
                } else {
                    $type = 'index_text';
                }
                if ($val['ALIAS'] == 'uploadjs') //uploadjs ATTR type for show
                    switch ($type) {
                        case 'index_picture':
                        case 'index_icon':
                            $row .= "{%if $Entity.$field %} <a class=\"bigpicture\"   href=\"{{asset('/uploads/" . $field . "/'~" . $Entity . "." . $field . ")}}\"><img style='max-width:33%;' src=\"{{asset('/uploads/" . $field . "/'~" . $Entity . "." . $field . ")}}\"></a> {% endif %}";
                            break;
                        case 'index_text':
                        default:
                            $row .= "\n{%if $Entity.$field %}\n<a class=\"bigpicture\"   href=\"{{asset('/uploads/" . $field . "/'~" . $Entity . "." . $field . ")}}\">{{" . "$Entity.$field" . "|split('_',2)[1]}}</a>\n{% endif %}"; // add html form
                            break;
                    }
            }
            if ($val['ALIAS'] == 'ckeditor' or $val['ALIAS'] == 'tinymce')
                $row .= "{{ $Entity.$field|striptags|u.truncate(200, '...', false)|cleanhtml$filters}}";
            if ($val['ALIAS'] == 'editorjs')
                $row .= "texte";
        }
        //timestamptable
        if (in_array($field, $timestamptable))
            $row .= "{{ $Entity.$field is not empty ? $Entity.$field|date('d/m à H:i', 'Europe/Paris')$filters}}";
        //for other
        if (!$row) {
            $row .= "{{ $Entity.$field$filters}}";
        }
        //ADD row
        $finalrow .= "\n<td" . $cursorSortable . ">$row</td>";
    }
}
//parse index.html with headers
$html = $this->twigParser($html, ['rows' => $finalrow]);
if ($this->input->getOption('origin')) {
    @mkdir('/app/templates/' . $entity);
    $this->saveFileWithCodes('/app/templates/' . $entity . '/index.html.twig', $html);
} else {
    @mkdir('/app/crudmick/crud');
    file_put_contents('/app/crudmick/crud/' . $Entity . '_index.html.twig', $html);
}
