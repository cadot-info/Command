<?php




namespace App\CMCommand;

use App\CMService\FileFunctions;
use App\CMService\String_functions;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * CrudmickCommand
He generate beautify crud for symfony
The options set in entity only for configurate twig, controller and type, it's magic!
This idea permit modify easily many files for the twig result
He can create in place by the parameter --orgin or in tempory directory crudmick/crud

The option minimum is:
- EXTEND for the twig extend (example: EXTEND=admin/index.html.twig get extend for the twig new, show, delete)
- PARTIE for firewall (example: PARTIE=admin get add admin in route for the controller )

The fields:
- uploadjs: for upload by ajax a simple file 
    - ATTR= for many show, template_... (example: new_text, index_picture...)
        - text: for show text
        - picture: widthxheight, exaple (autox100, 100x300 ...)
    - OPT:  
        - label: for replace label in index, example OPT=label=>House price
 
 */
class CrudmickCommand extends Command
{
    protected static $defaultName = 'crudmick:generateCrud';
    protected static $defaultDescription = 'Generate beautify Crud from doctrine entity';
    protected $path = 'crudmick/tpl/'; //path of tpl
    private $Entity;
    private $timestamptable;
    private $res;
    private $extend;
    private $sortable;
    private $output;
    private $input;
    private $io;

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;
        $this->input = $input;
        $this->io = new SymfonyStyle($this->input, $this->output);
    }
    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('entitie', InputArgument::OPTIONAL, 'name of entitie')
            ->addOption('origin', null, InputOption::VALUE_NONE, 'for write Controller and templates in your app directly')
            ->addOption('clean', null, InputOption::VALUE_NONE, 'for remove CM directories in your app directly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //var global

        $Entity = ucfirst($this->input->getArgument('entitie'));
        $timestamptable = ['createdAt', 'updatedAt', 'deletedAt'];
        $this->Entity = $Entity;
        $this->timestamptable = $timestamptable;
        //data of entity bu reflection class
        if ($Entity) {
            if (!file_Exists('/app/src/Entity/' . $Entity . '.php'))
                $this->io->error("This entity don't exist in /app/src/Entity");
            else {
                $this->getEffects(); // $res has many options (attr,opt,twig ... autre) of entity necessary for create
                $this->createType();
                $this->createController();
                $this->createNew();


                $this->createIndex();
            }
        } else $this->io->error('no Entity');
        return 1;
    }

    /**
     * Method getEffects
     *
     */
    private function getEffects()
    {
        require('getEffects.inc.php');
    }
    private function createIndex()
    {
        require('createIndex.inc.php');
    }

    private function createType()
    {
        require('createType.inc.php');
    }

    private function createNew()
    {
        require('createNew.inc.php');
    }
    private function createController()
    {
        require('createController.inc.php');
    }

    private function saveFileWithCodes(string $filename, string $html): int
    {
        //détection de l'extension
        $ext = substr(trim($filename), -3);
        if ($ext == 'php') {
            $baliseBegin = '/*¤';
            $baliseEnd = '¤*/';
            $patch = 0;
        } else {
            $baliseBegin = '{# ¤';
            $baliseEnd = '¤ #}';
            $patch = 2;
        }

        //récupération de l'ancien fichier
        if (\file_exists($filename)) {
            $ancien = file_get_contents($filename);
            //récupération des anciens codes à protéger et à remettre
            $codes = $this->getCodes($ancien, $baliseBegin, $baliseEnd);
            foreach ($codes as $code) {
                //on recherche la mark unique
                $pos = strpos($html, $code['mark']) - strlen($baliseBegin);
                $mark = "\n" . $baliseBegin . $code['mark'] . $baliseEnd . "\n";
                $codeEntier = "\n" . $baliseBegin . "code" . $baliseEnd . $code['code'] . $baliseBegin . "fincode" . $baliseEnd . "\n";
                if ($code['position'] == 'dessus') {
                    $html = substr($html, 0, $pos) . $mark . $codeEntier . substr($html, $pos + strlen($mark) - $patch);
                }
                if ($code['position'] == 'dessous') {
                    $html = substr($html, 0, $pos) . $codeEntier . $mark . substr($html, $pos + strlen($mark) - $patch);
                }
            }
        }
        return file_put_contents($filename, $html);
        //injection des anciens codes

    }

    /**
     * @param string code avec balise ¤code¤ et ¤fincode¤ 
     * 
     * @return array position=>dessus/dessous, code et mark
     */
    private function getCodes(string $string, $baliseBegin, $baliseEnd): array
    {
        $res = [];
        //on recherche la balise code
        $offset = 0;
        while (($pos = strpos($string, $baliseBegin . 'code' . $baliseEnd, $offset)) !== false) {
            $tab = [];
            $fin = strpos($string, $baliseBegin . 'fincode' . $baliseEnd, $pos);
            $fintotal = $fin + strlen($baliseBegin . 'fincode' . $baliseEnd);
            $tab['code'] = substr($string, $pos + strlen($baliseBegin . 'code' . $baliseEnd), $fin - $pos - strlen($baliseBegin . 'code' . $baliseEnd));
            //on regarde s'il y a une balise au dessus ou en dessous
            if (($pos - strrpos(substr($string, 0, $pos), $baliseEnd)) < 20) { //dessus
                $tab['position'] = 'dessus';
                $markbegin = strrpos(substr($string, 0, $pos), $baliseBegin);
                $tab['mark'] = substr($string, $markbegin + strlen($baliseBegin), strpos($string, $baliseEnd, $markbegin) - $markbegin - strlen($baliseEnd));
            }
            if (($markbegin = strpos($string, $baliseBegin, $fintotal)) !== false) { //dessous
                if (strpos($string, $baliseBegin, $fintotal) - $fintotal < 20) {
                    $tab['position'] = 'dessous';
                    $tab['mark'] = substr($string, $markbegin + strlen($baliseBegin), strpos($string, $baliseEnd, $markbegin) - $markbegin - strlen($baliseEnd));
                }
            }
            if (!isset($tab['mark'])) {
                $this->io->error('Pas de Mark détecté au dessus ou en dessus du code');
                exit();
            }
            //on ajoute au tableau
            $res[] = $tab;
            $offset = $fin + 10;
        }
        return $res;
    }

    /**
     * Method twigParser
     *
     * @param $html string twig with ¤...¤ for replacement
     * @param $tab array tableau des clefs à rechercher entre {{}} et à remplacer par value
     *
     * @return void
     */
    private function twigParser($html, $tab)
    {
        foreach ($tab as $key => $value) {
            $html = str_replace('//¤' . $key . '¤', $value, $html); // that in first
            $html = str_replace('¤' . $key . '¤', $value, $html);
        }
        return $html;
    }
    /**
     * Method searchInValue
     *
     * @param $array array 
     * @param $keyOfValue the key of value, for example OPT=title=>test, the keyofvalue=title
     *
     * @return string return the value
     */
    private function searchInValue($array, $keyOfValue): string
    {
        foreach ($array as $k => $v) {
            $val = strpos($v, '=>') ? explode('=>', $v)[0] : $v;
            if ($val == $keyOfValue) {
                $res = explode('=>', $v);
                return end($res);
                break;
            }
        }
        return false;
    }
    /**
     * Method is_relation
     *
     * @param $array array a array with key and value
     *
     * @return false or relation
     */
    private function is_relation($array)
    {
        $relationFind = false; //default value
        $relations = ['onetoone', 'manytoone', 'onetomany', 'manytomany'];
        foreach ($array as $value) {
            foreach ($relations as $relation) {
                if (strpos(strTolower($value), $relation) !== false) {
                    $relationFind = $relations[in_array(strToLower($value), $relations)];
                }
            }
        }
        return $relationFind;
    }
    private function show()
    {
        $res = $this->res;

        //creation de show
        $show = "{% extends '"  . $this->extend . "' %}";
        $show .= '
{% block title %}  ' . $Entity . ' 
    {% endblock %}
{% block body %} 
<h1> ' . $Entity . ' </h1>';
        //pour ne pas voir superadmin
        //                 $show .= "
        // {% if 'ROLE_SUPER_ADMIN' not in " . strtolower($Entity) . ".roles %}";
        $show .= '
<div class="col-12">
<ul class="list-group">';
        //on boucle sur les fields
        foreach ($res as $field => $val) {
            //gestion des classes spéciales
            $row = ''; //pour mémoriser le retours des spéciaux
            //recherche de la présence d'un type relation
            $relationFind = ''; // pour mémoriser le type de relation
            foreach ($val['AUTRE'] as $value) {
                if (
                    in_array(strToLower($value), $relations) !== false
                ) {
                    $relationFind = $relations[in_array(strToLower($value), $relations)];
                }
            }
            //si on a une relation
            if ($relationFind) {
                $row = "\n<td>{{" . strtolower($Entity) . "." . $field . "|json_encode";
            }
            //si on à un no_show
            if (isset($val['ATTR']['no_show'])) {
            }
            //si on a un choices
            if (isset($val['OPT']['choices'])) {
                $choices = str_replace('[', '', $val['OPT']['choices']);
                $choices = str_replace(']', '', $choices);
                $choices = explode(',', $choices);
                $resChoices = '';
                foreach ($choices as $k => $v) {
                    $tab = explode('=>', $v);
                    if (isset($tab[1])) {
                        $resChoices .= $tab[0] . ':' . $tab[1] . ",";
                    } else {
                        $resChoices .= $v . ':' . $v . ",";
                    }
                }
                $row = ucfirst($field) . "{% set options={" . $resChoices . "} %}";
                $row .= "
                    {% set res=[] %}
                    {% for key,option in options %}
                    {% if option in " . strtolower($Entity) . "." . $field . " %}
                    {% set res=res|merge([key]) %}
                    {% endif %}
                    {% endfor %}
                    {{res|json_encode";
            }
            //is on est pas dans les cas ci-dessus
            if (!$row) {
                $row = "\n<li class=\"list-group-item\">
        <h6>" . ucfirst($field) . "</h6>\n
        <hr>";
                //si on a des ALIAS
                if (isset($val['ALIAS'])) {
                    //on commence par ALIAS upoloadjs
                    if ($val['ALIAS'] == 'uploadjs') {
                        //on cherche si on a un type de uploadjs
                        $typefile = 'texte'; // valeur par défaut pour uploadjs
                        if (isset($val['ATTR'])) {
                            foreach ($val['ATTR'] as $attribu) {
                                $tabfile = explode('=>', $attribu);
                                if ($tabfile[0] == 'image' || $tabfile[0] == 'icone') {
                                    $typefile = $tabfile[0];
                                }
                            }
                        }
                        //type image
                        if ($typefile == 'image') {
                            $sizef = "";
                            $size = explode('x', $tabfile[1]);
                            if (trim($size[0]) == '0') {
                                $sizef = 'height=' . $size[1] . 'px';
                            } else {
                                $sizef = 'width=' . $size[0] . 'px';
                            }
                            $row .= "{%if " . strtolower($Entity) . "." . $field . " %}" .
                                "<a data-toggle='popover-hover' data-original-title=\"\" title=\"\" data-img=\"{{voir('" . $field . "/'~" . strtolower($Entity) . "." . $field . ")}}\"><img " . $sizef . " src=\"{{voir('" . $field . "/'~" . strtolower($Entity) . "." . $field . ")}}\"></a> {% endif %}";
                        }
                        //type icone
                        if ($typefile == 'icone') {
                            $row .= "{%if " . strtolower($Entity) . " . " . $field . " %}" .
                                "<a data-toggle='popover-hover' data-original-title=\"\" title=\"\" data-img=\"{{voir('" . $field . "/'~" . strtolower($Entity) . "." . $field . ")}}\"><img src=\"{{getico('" . $field . "/'~" . strtolower($Entity) . "." . $field . ")}}\"></a> {% endif %}";
                        }
                        //type texte
                        if ($typefile == 'texte') {
                            $row .= '<label class="exNomfile">' . "{{" . strtolower($Entity) . "." . $field . "}}</label>";
                        }
                    } else {   //si c'est un autre ALIAS
                        $row .= '{{' . strtolower($Entity) . '.' . $field;
                    }
                } else {   //si c'est pas un ALIAS
                    if (in_array($field, $timestamptable)) {
                        $row .= '{{' . strtolower($Entity) . '.' . $field;
                    }
                }
                //gestion des filtres à ajouter
                //on a des filtres
                $filtres = '';
                if (isset($val['TWIG'])) {
                    foreach ($val['TWIG'] as $twig) {
                        $filtres .= "|" . $twig;
                    }
                }
                //timestamptable
                if (in_array($field, $timestamptable)) {
                    $filtres .= ' is empty ? "" :' . $Entity . ' . ' . $field . '|date("d/m à H:i", "Europe/Paris")';
                }

                //on vérifie s'il faut l'afficher (pas de no_index et pas du type relation
                if (!isset($val['ATTR']['no_show'])) {
                    $show .= $row . $filtres;
                    //pour le type ckeditor on ajoute un filtre
                    if (isset($val['ALIAS'])) {
                        if ($val['ALIAS'] == 'ckeditor' or $val['ALIAS'] == 'editorjs' or $val['ALIAS'] == 'tinymce') {
                            $show .= '|cleanhtml';
                        }
                    }
                    //on ferme pour tous les types sauf uploadjs
                    if (isset($val['ALIAS'])) {
                        if ($val['ALIAS'] != 'uploadjs') {
                            $show .= "}}";
                        }
                    } else {
                        $show .= "}}";
                    }
                }
            } //cas spéciaux
        } //boucle sur les fields
        $show .= "
    </ul>
    </div>";

        $show .= "\n" . '<a href="{{ path(\'' . strtolower($Entity) . '_index\') }}" class="btn btn-secondary mr-2" type="button">Revenir à la liste</button></a>';

        $show .= "{% endblock %}";
        if ($input->getOption('origin')) {
            @mkdir('/app/templates/' . strTolower($Entity));
            $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $Entity;
            @mkdir($dir);
            @rename('/app/templates/' . strTolower($Entity) . '/show.html.twig', $dir);
            file_put_contents('/app/templates/' . strTolower($Entity) . '/show.html.twig', $show);
        } else {
            @mkdir('/app/crudmick/crud');
            file_put_contents('/app/crudmick/crud/' . $Entity . '_show.html.twig', $show);
        }
    }
}
