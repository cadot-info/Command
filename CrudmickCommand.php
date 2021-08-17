<?php




namespace App\CMCommand;

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
- file: for upload by ajax a simple file 
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
    protected $path = 'src/CMService/tpl/'; //path of tpl
    private $Entity;
    private $timestamptable;
    private $res;
    private $r;
    private $input;


    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('entitie', InputArgument::OPTIONAL, 'name of entitie')
            ->addOption('origin', null, InputOption::VALUE_NONE, 'for write Controller and templates in your app directly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //var global
        $io = new SymfonyStyle($input, $output);
        $Entity = ucfirst($input->getArgument('entitie'));
        $timestamptable = ['createdAt', 'updatedAt', 'deletedAt'];
        $this->input = $input;
        $this->Entity = $Entity;
        $this->timestamptable = $timestamptable;

        //data of entity bu reflection class
        if ($Entity) {
            if (!file_Exists('/app/src/Entity/' . $Entity . '.php'))
                $io->error("This entity don't exist in /app/src/Entity");
            else {
                $this->getEffects(); // $res has many options (attr,opt,twig ... autre) of entity necessary for create
                //minimum options for crudmick
                if (!isset($this->res['id']['EXTEND'])) {
                    $io->error('Please get a EXTEND for id (example: EXTEND=admin/admin.html.twig, used bu twigs)');
                    exit();
                }
                if (!isset($this->res['id']['PARTIE'])) {
                    $io->error('Please get a PARTIE for id (example: PARTIE=admin, used by controller)');
                    exit();
                }
                $this->createType();
                $this->createController();
                $this->createNew();


                $this->createIndex();

                dd();
                $res = $this->res;

                //creation de show
                $show = "{% extends '"  . $res['id']['EXTEND'] . "' %}";
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
                            //on commence par ALIAS file
                            if ($val['ALIAS'] == 'file') {
                                //on cherche si on a un type de file
                                $typefile = 'texte'; // valeur par défaut pour file
                                if (isset($val['ATTR']))
                                    foreach ($val['ATTR'] as $attribu) {
                                        $tabfile = explode('=>', $attribu);
                                        if ($tabfile[0] == 'image' || $tabfile[0] == 'icone') {
                                            $typefile = $tabfile[0];
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
                            if (in_array($field, $timestamptable))
                                $row .= '{{' . strtolower($Entity) . '.' . $field;
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
                        if (in_array($field, $timestamptable))
                            $filtres .= ' is empty ? "" :' . $Entity . ' . ' . $field . '|date("d/m à H:i", "Europe/Paris")';

                        //on vérifie s'il faut l'afficher (pas de no_index et pas du type relation
                        if (!isset($val['ATTR']['no_show'])) {
                            $show .= $row . $filtres;
                            //pour le type ckeditor on ajoute un filtre
                            if (isset($val['ALIAS']))
                                if ($val['ALIAS'] == 'ckeditor' or $val['ALIAS'] == 'editorjs')
                                    $show .= '|cleanhtml';
                            //on ferme pour tous les types sauf file
                            if (isset($val['ALIAS'])) {
                                if ($val['ALIAS'] != 'file') {
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
                //creation du controller



                /* ------------------------------------------------------------------------------------------------------------------ */
                /*                                                                                             GENERATION DU FORMTYPE */
                /* ------------------------------------------------------------------------------------------------------------------ */
                $relation_use = [];
                $collection_use = [];
                $biblio_use = [];
                $FT = '';
                //on supprime des fields les timestamptables
                unset($res['updatedAt']);
                unset($res['createdAt']);
                unset($res['deletedAt']);

                //on boucle sur les fields
                foreach ($res as $field => $val) {

                    //gestion des classes spéciales
                    $row = ''; //pour mémoriser le retours des spéciaux
                    //recherche de la présence d'un type relation
                    $relationFind = ''; // pour mémoriser le type de relation
                    foreach ($val['AUTRE'] as $value) {
                        if (in_array(strToLower($value), $relations) !== false) {
                            $relationFind = $relations[in_array(strToLower($value), $relations)];
                        }
                    }
                    $TYPE = 'null';
                    $resAttr = array(); //stock des attrs
                    $resOpt = array(); //stock des opts
                    //attribut unique pour le mask qui donne aussi le type
                    $tab_ALIAS = ['file' => 'file', 'hidden' => 'hidden', 'radio' => 'radio', 'date' => 'date', 'password' => 'password', 'centimetre' => 'CentiMetre', 'metre' => 'metre', 'prix' => 'money', 'ckeditor' => 'CKEditor', 'editorjs' => 'hidden',  'texte_propre' => 'text', 'email' => 'email', 'color' => 'color', 'phonefr' => 'tel', 'code_postal' => 'text', 'km' => 'number', 'adeli' => 'number'];
                    // if (isset($val['ALIAS'])) {
                    //     //si on connait cet alias on met son type dans add et on ajoute le use et on ajoute l'alias dans les attr
                    //     if (array_key_exists($val['ALIAS'], $tab_ALIAS) !== false) {
                    //         $TYPE = ucfirst($tab_ALIAS[$val['ALIAS']]) . "Type::class";
                    //         $resAttr[] = "'data-inputmask' => \"'alias': '" . $val['ALIAS'] . "'\"";
                    //         if ($tab_ALIAS[$val['ALIAS']] == 'money') {
                    //             $resAttr[] = "'divisor' => 100";
                    //         }
                    //         //on ajoute le type dans les use si pas existant
                    //         if (!in_array(ucfirst($tab_ALIAS[$val['ALIAS']]), $biblio_use)) {
                    //             $biblio_use[] = ucfirst($tab_ALIAS[$val['ALIAS']]);
                    //         }
                    //     } else {
                    //         //sinon on met juste le type dans add
                    //         $TYPE = $field;
                    //     }
                    //     //pour editorjs
                    //     if ($val['ALIAS'] == 'editorjs') $resAttr[] = "'class'=>'editorjs'";
                    // }


                    //travail sur ATTR

                    if (isset($val['ATTR'])) {
                        foreach ($val['ATTR'] as $attr) {
                            //si on a une config par =>
                            if (strpos('=>', $attr) !== false) {
                                $tab = explode('=>', $attr);
                                $resAttr[] = "'$tab[0]'=>$tab[1]";
                            } else {
                                $resAttr[] = "'$attr'";
                            }
                        }
                    }
                    //travail sur OPT
                    if (isset($val['OPT'])) {
                        foreach ($val['OPT'] as $opt) {
                            $tabopt = explode('=>', $opt);
                            if ('choices' == $tabopt[0]) {
                                $choices = str_replace('[', '', $tabopt[1]);
                                $choices = str_replace(']', '', $choices);
                                $choices = explode(',', $choices);
                                $resChoices = '';
                                foreach ($choices as $k => $v) {
                                    $tab = explode('=>', $v);
                                    if (isset($tab[1])) {
                                        $resChoices .= $tab[0] . '=>' . $tab[1] . ",";
                                    } else {
                                        $resChoices .= $v . '=>' . $v . ",";
                                    }
                                }
                                //on ajoute choice dans les librairies à charger
                                $TYPE = "ChoiceType::class";
                                if (!in_array('Choice', $biblio_use)) {
                                    $biblio_use[] = 'Choice';
                                }
                                //on ajoute les choices
                                $resOpt[] = "'choices'=>[" . $resChoices . "]";
                            } else
                                //pour les autres
                                $resOpt[] = "'$tabopt[0]'=>" . substr($opt, strlen($tabopt[0]) + 2);
                        }
                    }

                    //on ajoute dans $resOpt si on a un type file et required=false (pour pouvoir ne rien changer) pour l'envoie du formulaire
                    if (isset($val['ALIAS'])) if ($val['ALIAS'] == 'file') $resOpt[] = "'data_class' => null,'required' => false";

                    if ($field != 'id') {
                        $FT .= "\n->add('$field',$TYPE,['attr'=>[" . implode(',', $resAttr) . "]";
                        if ($resOpt) {
                            $FT .= "," . implode(',', $resOpt);
                        }
                        $FT .= "])";
                    }
                }
            } //fin de la boucle sur les fields
            //dd();
            $finalft = '<?php
namespace App\Form;

use App\Entity\\' . $Entity . ' ;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;' . "\n";

            //collection
            if (sizeof($relation_use) > 0)
                $finalft .= "\nuse Symfony\Bridge\Doctrine\Form\Type\EntityType;\n";

            foreach ($relation_use as $nameentity) {
                $finalft .= "\nuse App\Entity\\" . $nameentity . ";\n";
            }
            //relation
            if (sizeof($collection_use) > 0)
                $finalft .= "\nuse Symfony\Component\Form\Extension\Core\Type\CollectionType;\n";


            foreach ($collection_use as $nameentity) {
                $finalft .= "\nuse App\Entity\\" . $nameentity . ";\n";
            }


            foreach ($biblio_use as $biblio) {
                if ($biblio == 'CKEditor')
                    $finalft .= "use FOS\CKEditorBundle\Form\Type\\" . $biblio . "Type;\n";
                elseif ($biblio == 'Metre')
                    $finalft .= "use App\\Form\Type\\" . $biblio . "Type;\n";
                elseif ($biblio == 'CentiMetre')
                    $finalft .= "use App\\Form\Type\\" . $biblio . "Type;\n";
                else
                    $finalft .= "use Symfony\Component\Form\Extension\Core\Type\\" . $biblio . "Type;\n";
            }


            $finalft .= 'class ' . $Entity . 'Type extends AbstractType
{
public function buildForm(FormBuilderInterface $builder, array $AtypeOption)
{
$builder';
            $finalft .= $FT . ';}

public function configureOptions(OptionsResolver $resolver)
{
$resolver->setDefaults([
            \'data_class\' => ' . $Entity . '::class,
        ]);
    }
}
';
            if ($input->getOption('origin')) {
                $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $Entity;
                @rename('/app/src/Form/' . $Entity . 'Type.php', $dir);
                file_put_contents('/app/src/Form/' .  $Entity . 'Type.php', $finalft);
            } else {
                file_put_contents('/app/crudmick/crud/' . $Entity . 'Type.php', $finalft);
            }
        } else {
            $io->error('Please get the name of entitie');
        }
        return Command::SUCCESS;
    }

    /**
     * Method getEffects
     *
     */
    private function getEffects()
    {
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
                        $docClean = substr($docClean, strlen('* '));
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
        $this->res = $res;
    }
    private function createIndex()
    {
        $res = $this->res;
        $Entity = $this->Entity;
        $entity = strToLower($Entity);
        $timestamptable = $this->timestamptable;
        $html = $this->twigParser(file_get_contents($this->path . 'index.html.twig'), array('entity' => $entity, 'Entity' =>
        $Entity, 'extends' => $res['id']['EXTEND']));
        //code for sortable ATTR
        if (isset($res['id']['ATTR']))
            if ($this->searchInValue($res['id']['ATTR'], 'sortable') !== false)
                $html = $this->twigParser($html, ['sortable' => "{% set list=findOneBy('sortable',{'entite':'$Entity'}) %}\n{% if list != null %}<input type='hidden' id='ex_sortable' value='{{list.Ordre}}'>\n{% endif %}<input entite='$Entity' id='save_sortable' type='hidden'>"]);

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
        $finalrow = ''; //content row of table
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
                    $row .= "\n<td>{% for item in $Entity.$field %}{{ item }},{% endfor %}";
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
                    // file has ATTR file, text or picture or nothoing
                    if ($val['ALIAS'] == 'file' || $val['ALIAS'] == 'collection') {
                        //get the type by ATTR with default text
                        if (isset($val['ATTR'])) {
                            $attr = explode('=>', $val['ATTR'][0]);
                            $type = $attr[0];
                        } else {
                            $type = 'index_text';
                        }
                        if ($val['ALIAS'] == 'file') //file ATTR type for show
                            switch ($type) {
                                    //icon for picture
                                case 'index_picture':
                                case 'index_icon':
                                    $row .= "\n{%if $entity.$field %}\n<img src=\"{{asset('$field/$entity.$field')}}\"> \n{% endif %}"; // add html form
                                    break;
                                case 'index_text':
                                default:
                                    $row .= "{%if $Entity.$field %} <a class='bigpicture'   href=\"{{asset('/uploads/" . $field . "/'~" . $Entity . "." . $field . ")}}\"><img src=\"{{asset('" . $field . "/'~" . $Entity . "." . $field . ")}}\"></a> {% endif %}";
                                    break;
                            }
                    }
                    if ($val['ALIAS'] == 'ckeditor' || $val['ALIAS'] == 'editorjs')
                        $row .= "{{ $Entity.$field|striptags|u.truncate(200, '...', false)|cleanhtml$filters}}";
                }
                //timestamptable
                if (in_array($field, $timestamptable))
                    $row .= "{{ $Entity.$field is not empty ? $Entity.$field|date('d/m à H:i', 'Europe/Paris')$filters}}";
                //for other
                if (!$row) {
                    $row .= "{{ $Entity.$field$filters}}";
                }
                //ADD row
                $finalrow .= "\n<td>$row</td>";
            }
        }
        //parse index.html with headers
        $html = $this->twigParser($html, ['rows' => $finalrow]);
        if ($this->input->getOption('origin')) {
            @mkdir('/app/templates/' . $entity);
            file_put_contents('/app/templates/' . $entity . '/index.html.twig', $html);
        } else {
            @mkdir('/app/crudmick/crud');
            file_put_contents('/app/crudmick/crud/' . $Entity . '_index.html.twig', $html);
        }
    }

    private function createType()
    {
        $SF = new String_functions(); //tools for string
        $res = $this->res;
        $Entity = $this->Entity;
        $entity = strToLower($Entity);
        $timestamptable = $this->timestamptable;
        $html = $this->twigParser(file_get_contents($this->path . 'type.php'), array('entity' => $entity, 'Entity' => $Entity, 'extends' => $res['id']['EXTEND']));
        //ALIAS to type
        $tab_ALIAS = ['file' => 'file', 'hidden' => 'hidden', 'radio' => 'radio', 'date' => 'date', 'password' => 'password', 'centimetre' => 'CentiMetre', 'metre' => 'metre', 'prix' => 'money', 'autocomplete' => 'text', 'ckeditor' => 'CKEditor', 'editorjs' => 'hidden',  'texte_propre' => 'text', 'email' => 'email', 'color' => 'color', 'phonefr' => 'tel', 'code_postal' => 'text', 'km' => 'number', 'adeli' => 'number'];
        //loop on fields
        $twigNew = []; // array for stock parser
        $twigNew['form_rows'] = ''; //contains string for replace form_rows
        //remove timestamptables
        unset($res['updatedAt']);
        unset($res['createdAt']);
        unset($res['deletedAt']);
        $adds = ' $builder'; //contents add of builder
        $uses = ''; //for parse uses in php file
        $collections = ''; //content collections for use
        $biblio_use = []; //content uses
        foreach ($res as $field => $val) {
            $type = 'null'; //stock type in fcuntion of tab_ALIAS
            $Field = ucfirst($field);
            $attrs = []; //array of attributes
            $opts = []; //array of options
            //if it's a relation field
            if ($this->is_relation($val['AUTRE']) !== false) {
                //determination of entity for add in use
                foreach ($val['AUTRE'] as $key => $value) {
                    $entityRelation = ($SF->chaine_extract($value, 'targetEntity=', '::class'));
                    if ($val['ALIAS'] == 'collection') {
                        $type = "CollectionType::class";
                        $opts[] = "'entry_type' => FichierType::class,'entry_options' => ['label' => false],'allow_add' => true,'by_reference' => false,'allow_delete' => true,'required' => false,";
                        $attrs[] = "'class' => 'collection'";
                        $collections .= "\nuse App\Form\\$entityRelation" . "Type;\n";
                    } else
                    if ($entityRelation) $collections .= "\nuse App\Entity\\$entityRelation;\n";
                }
            }
            if ($field != 'id') {
                //for use by ALIAS
                if (isset($val['ALIAS']))
                    if (isset($tab_ALIAS[$val['ALIAS']]))
                        if (!in_array(ucfirst($tab_ALIAS[$val['ALIAS']]), $biblio_use)) {
                            $biblio_use[] = ucfirst($tab_ALIAS[$val['ALIAS']]);
                            $type = ucfirst($tab_ALIAS[$val['ALIAS']]) . "Type::class";
                        }
                //for use by OPT
                if (isset($val['OPT']))
                    if ($values = $this->searchInValue($val['OPT'], 'choices')) {
                        if (!in_array('Choice', $biblio_use))
                            $biblio_use[] = 'Choice';
                        $type = 'Choice' . "Type::class";
                    }
                $adds .= "\n->add('$field',$type";
                //for attributes
                if (isset($val['ATTR'])) {
                    foreach ($val['ATTR'] as $key => $value) {
                        if (isset(explode('=>', $value)[1]))
                            $attrs[] = "'" . explode('=>', $value)[0] . "'=>" . explode('=>', $value)[1];
                    }
                }
                //for options
                if (isset($val['OPT'])) {
                    foreach ($val['OPT'] as $key => $value) {
                        if (isset(explode('=>', $value)[1])) {
                            //exception for choices
                            if (explode('=>', $value)[0] == 'choices') {
                                $resChoices = [];
                                //for only value
                                if (substr_count($value, '=>') == 1) {
                                    foreach (explode(',', substr(trim(explode('=>', $value)[1]), 1, -1)) as $choix) {
                                        $resChoices[] = $choix . "=>" . $choix;
                                    }
                                    $opts[] = "'choices'=>[" . implode(',', $resChoices) . "]";
                                } else //for key and value
                                    $opts[] =  str_replace("choices=>", "'choices'=>[", $value) . "]";
                            }
                        }
                    }
                }
                //render
                if (count($attrs) or count($opts)) $adds .= ',[';
                if (count($attrs)) $adds .= "'attr'=>[" . implode(',', $attrs) . "]";
                if (count($attrs) and count($opts)) $adds .= ',';
                if (count($opts)) $adds .=  implode(',', $opts);
                if (count($attrs) or count($opts)) $adds .= ']';
                $adds .= ")";
            }
        }
        //create uses
        if ($collections) $uses .= $collections . "\nuse Symfony\Component\Form\Extension\Core\Type\CollectionType;\n";
        foreach ($biblio_use as $biblio) {
            if ($biblio == 'CKEditor')
                $uses .= "use FOS\CKEditorBundle\Form\Type\\" . $biblio . "Type;\n";
            else
                $uses .= "use Symfony\Component\Form\Extension\Core\Type\\" . $biblio . "Type;\n";
        }

        //parse type.php with headers
        $html = $this->twigParser($html, ['adds' => $adds, 'uses' => $uses]);
        $html = \str_replace('namespace App\CMService\tpl;', 'namespace App\Form;', $html);
        //create file
        if ($this->input->getOption('origin'))
            file_put_contents('/app/src/Form/' .  $Entity . 'Type.php', $html);
        else
            file_put_contents('/app/crudmick/crud/' . $Entity . 'Type.php', $html);
    }

    private function createNew()
    {
        $res = $this->res;
        $Entity = $this->Entity;
        $entity = strToLower($Entity);
        $timestamptable = $this->timestamptable;
        $html = $this->twigParser(file_get_contents($this->path . 'new.html.twig'), array('entity' => $entity, 'Entity' => $Entity, 'extends' => $res['id']['EXTEND']));

        //loop on fields
        $twigNew = []; // array for stock parser
        $twigNew['form_rows'] = ''; //contains string for replace form_rows
        foreach ($res as $field => $val) {
            $Field = ucfirst($field);
            //jump timestamptables and id
            if (in_array($field, $timestamptable) === false and $field != 'id') {
                $no_new = false;
                //verify show for new
                if (isset($val['ATTR'])) $no_new = in_array('no_new', $val['ATTR']) ? true : false;
                //if no_new insert in twig, field is rendered
                $twigNew['form_rows'] .= $no_new ? ' {% do form.' . $field . '.setRendered() %}' . "\n" : '{{ form_row(form.' . $field . ') }}' . "\n";
                //All fields except no_new and id
                if ($no_new == false) {

                    if (isset($val['ALIAS'])) {
                        //ALIAS file
                        // he has ATTR file, text or picture
                        if ($val['ALIAS'] == 'file') {
                            $twigNew['form_rows'] .= '<div class="form-group">';
                            //get the type by ATTR with default text
                            if (isset($val['ATTR'])) {
                                $attr = explode('=>', $val['ATTR'][0]);
                                $type = $attr[0];
                            } else {
                                $type = 'new_text';
                            }
                            //file ATTR type for show
                            switch ($type) {
                                case 'new_picture':
                                    $size = explode('x', $attr[1]);
                                    $sizef = trim($size[0]) == 'auto' ? 'height=' . $size[1] . 'px' : 'width=' . $size[0] . 'px'; //render the html size
                                    $twigNew['form_rows'] .= "\n" . "{%if $entity.$field %}\n<img $sizef src=\"{{voir('$field/~$entity.$field')}}\">\n{% endif %}"; // add html form
                                    break;
                                case 'new_icon':
                                    $twigNew['form_rows'] .= "\n" . "{%if $entity.$field %}\n<img src=\"{{getico('$field/$entity.$field')}}\"> \n{% endif %}"; // add html form
                                    break;
                                case 'new_text':
                                default:
                                    $twigNew['form_rows'] .= "\n<label class='exNomfile'>{{ $entity.$field }}</label>";
                                    break;
                            }
                            $twigNew['form_rows'] .= "\n</div>\n";
                        }
                        //for editorjs
                        if ($val['ALIAS'] == 'editorjs')  $twigNew['form_rows'] .= "<div id='editorjs'></div>\n";
                        //for autocomplete.js
                        if ($val['ALIAS'] == 'autocomplete')
                            $twigNew['form_rows'] .= "<input type='hidden' class='autocomplete' data-id='$entity" . "_" . "$field' value='{{autocomplete$Field}}'>\n";
                    }
                }
            }
        }
        $html = $this->twigParser($html, $twigNew);
        if ($this->input->getOption('origin')) {
            @mkdir('/app/templates/' . $entity);
            file_put_contents('/app/templates/' . $entity . '/new.html.twig', $html);
        } else {
            @mkdir('/app/crudmick/crud');
            file_put_contents('/app/crudmick/crud/' . $Entity . '_new.html.twig', $html);
        }
    }
    private function createController()
    {
        $res = $this->res;
        $Entity = $this->Entity;
        $entity = strToLower($Entity);
        $timestamptable = $this->timestamptable;
        $html = $this->twigParser(file_get_contents($this->path . 'controller.php'), array('entity' => $entity, 'Entity' => $Entity, 'extends' => $res['id']['EXTEND']));
        //lop for autocomplete
        $autocompleteRender = '';
        foreach ($res as $field => $val) {
            $Field = ucfirst($field);
            if (isset($val['ALIAS'])) {
                if ($val['ALIAS'] == 'autocomplete') {
                    $autocompleteRender .= "'autocomplete$Field'=>\$functionEntitie->getAllOfFields('$entity','$field'),";
                }
            }
        }
        //parse the html with autocomplete
        if ($autocompleteRender) {
            $html = $this->twigParser($html, array('autocompleteRender' => substr($autocompleteRender, 0, -1), 'autocompleteService' => 'use App\CMService\FunctionEntitie'));
            //specific replacement for php for include Service
            $html = str_replace('new(Request $request)', 'new(Request $request,FunctionEntitie $functionEntitie)', $html);
            $html = str_replace('edit(Request $request', 'edit(Request $request,FunctionEntitie $functionEntitie', $html);
        }
        //create file
        if ($this->input->getOption('origin'))
            file_put_contents('/app/src/Controller/' .  $Entity . 'Controller.php', $html);
        else
            file_put_contents('/app/crudmick/crud/' . $Entity . 'Controller.php', $html);
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
}
