<?php

namespace App\CMCommand;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;



class CrudmickCommand extends Command
{
    protected static $defaultName = 'crudmick:generateCrud';
    protected static $defaultDescription = 'Generate beautify Crud from doctrine entity';

    protected function configure(): void
    {
        $this
            ->setDescription(self::$defaultDescription)
            ->addArgument('entitie', InputArgument::OPTIONAL, 'name of entitie')
            ->addOption('origin', null, InputOption::VALUE_NONE, 'for write Controller and templates in your app directly');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $entitie = ucfirst($input->getArgument('entitie'));
        //récupération des données de l'entitée
        if ($entitie) {
            if (!file_Exists('/app/src/Entity/' . $entitie . '.php'))
                $io->error("This entity don't exist in /app/src/Entity");
            else {
                $class = 'App\Entity\\' . $entitie;
                $r = new \ReflectionClass(new $class);
                //tableau de recherche
                $aSupprimer = array('/**', '*/');
                $mCrud = array('pour éviter retour false', 'ATTR', 'PARTIE', 'EXTEND', 'OPT', 'ALIAS', 'OPT', 'TPL', 'TWIG');
                $FUnique = array('ALIAS', 'EXTEND', 'PARTIE');
                $res = array(); // retour de la recherche
                foreach ($r->getProperties() as $property) {
                    $name = $property->getName();
                    $docs = (explode("\n", $property->getDocComment()));
                    //on liste les docs
                    foreach ($docs as $doc) {
                        //suppression des balises à supprimer
                        if (!in_array(trim($doc), $aSupprimer)) {
                            //suppression des espaces inutiles
                            $docClean = trim($doc);
                            if (substr($docClean, 0, strlen('* '))) {
                                $docClean = substr($docClean, strlen('* '));
                            }
                            //on regarde si c'est un champ crudmick
                            $posEgale = strpos($docClean, '=');
                            if ($posEgale !== false) {
                                if ($type = array_search(substr($docClean, 0, $posEgale), $mCrud)) {
                                    //si ce field est unique
                                    if (in_array($mCrud[$type], $FUnique) !== false) {
                                        $res[$name][$mCrud[$type]] = substr($docClean, $posEgale + 1);
                                    } else { //si ce field peux prendre plusierus valeurs
                                        $res[$name][$mCrud[$type]][] = substr($docClean, $posEgale + 1);
                                    }
                                } else { //si il ya un = mais que ce n'est pas un mot réservé de crudmick
                                    $res[$name]['AUTRE'][] = $docClean;
                                }
                            } else {
                                //sinon on l'ajoute simplement
                                $res[$name]['AUTRE'][] = $docClean;
                            }
                        }
                    }
                }

                //vérification des champs obligatoires
                if (!isset($res['id']['EXTEND'])) {
                    $io->error('Please get a EXTEND for id (example: EXTEND=admin/admin.html.twig)');
                    exit();
                }
                if (!isset($res['id']['PARTIE'])) {
                    $io->error('Please get a PARTIE for id (example: PARTIE=admin)');
                    exit();
                }

                /* ------------------------------------------------------------------------------------------------------------------ */
                /*                                                                                               CREATION DE NEW/EDIT */
                /* ------------------------------------------------------------------------------------------------------------------ */
                $new = '{% extends \'' . $res['id']['EXTEND'] . '\' %}';
                $new .= "
{% set route = path(app.request.attributes.get('_route'), app.request.attributes.get('_route_params')) %}
{% set action = path(app.request.attributes.get('_route'), app.request.attributes.get('_route_params')) | split('/') |
last %}

{% block title %}
    {% if action=='new'  %}Création
        {% elseif action=='copy' %}Duplication 
        {% elseif action=='edit' %}Edition
    {% endif %} " . $entitie . "
{% endblock %}

{% block body %}

<div class=\"col-12 text-center\">
    <h1>{% if action=='new'  %}Créer {% elseif action=='copy' %}Dupliquer {% elseif action=='edit' %}Editer{% endif %} " . $entitie . "
        <span class=\"text-right\">
            <h5>taille maxi d'envoie:{{upload_max()}} Mo</h5>
        </span>
    </h1>
</div>
<div class=\"col-12\">
    {{ form_start(form) }}
";
                //on boucle sur les fields sauf id
                foreach ($res as $field => $val) {
                    $no_new = false;
                    //on vérifie que ce champ doit être affiché
                    if (isset($val['ATTR']))
                        foreach ($val['ATTR'] as  $attr) {
                            if ($attr == 'no_new') $no_new = true;
                        }
                    //si no_new est vrai on dit qu'il est rendu
                    if ($no_new)
                        $new .= ' {% do form.' . $field . '.setRendered() %}';
                    //on affiche si ce n'est pas id et si no_new est faux
                    if ($field != 'id' and $no_new == false) {
                        $new .= "
    {{ form_row(form." . $field . ") }} \n";

                        //on boucle sur les fields(ALIAS,ATTR...)
                        if (isset($val['ALIAS'])) {
                            //on commence par ALIAS fichier
                            if ($val['ALIAS'] == 'fichier') {
                                $new .= '<div class="form-group">';
                                //on boucle sur le type à afficher
                                //on récupère l'attr
                                if (isset($val['ATTR'])) {
                                    $attr = explode('=>', $val['ATTR'][0]);
                                    $type = $attr[0];
                                } else $type = 'texte';
                                switch ($type) {
                                    case 'image':
                                        $sizef = "";
                                        $size = explode('x', $attr[1]);
                                        if (trim($size[0]) == '0') {
                                            $sizef = 'height=' . $size[1] . 'px';
                                        } else {
                                            $sizef = 'width=' . $size[0] . 'px';
                                        }
                                        $new .= "
    {%if " . strToLower($entitie) . "." . $field . " %}
        <img " . $sizef . " src=\"{{voir('" . $field . "/'~" . strToLower($entitie) . "." . $field . ")}}\"> 
    {% endif %}";
                                        break;
                                    case 'icone':
                                        $new .= "
    {%if " . strToLower($entitie) . " . " . $field . " %}
    <img src=\"{{getico('" . $field . "/'~" . strToLower($entitie) . "." . $field . ")}}\"> 
    {% endif %}";
                                        break;
                                    case 'texte':
                                    default:
                                        $new .= "
    <label class='exNomFichier'>
        {{" . strToLower($entitie) . "." . $field . "}}
    </label>";
                                        break;
                                }
                                $new .= "
</div>";
                            }
                        }
                    }
                }
                $new .= '
 <button class="btn btn-primary" type="submit">
    {% if action==\'new\'  %}Créer 
    {% else %}Mettre à jour
    {% endif %}
 </button>
<a href="{{ path(\'' . strtolower($entitie) . '_index\') }}">
    <button class="btn btn-secondary" type="button">Revenir à la liste</button>
</a>
<input type="hidden" id="token" value="{{ csrf_token(\'upload\')}}" />
{{ form_end(form) }}
</div>
{% endblock %}
                    ';


                if ($input->getOption('origin')) {
                    @mkdir('/app/templates/' . strTolower($entitie));
                    $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $entitie;
                    @mkdir($dir);
                    @rename('/app/templates/' . strTolower($entitie) . '/new.html.twig', $dir);
                    file_put_contents('/app/templates/' . strTolower($entitie) . '/new.html.twig', $new);
                } else {
                    @mkdir('/app/crudmick/crud');
                    file_put_contents('/app/crudmick/crud/' . $entitie . '_new.html.twig', $new);
                }
                /* ------------------------------------------------------------------------------------------------------------------ */
                /*                                                                                                  CREATION DE INDEX */
                /* ------------------------------------------------------------------------------------------------------------------ */
                $index = '{% extends \'' . $res['id']['EXTEND'] . '\' %}';
                $index .= '
{% block title %}  ' . $entitie . ' 
    {% endblock %}
{% block body %} 
<h1> ' . $entitie . ' </h1>';
                //on installe sortable si demandé
                if (isset($res['id']['ATTR'])) {
                    if (array_search('sortable', $res['id']['ATTR']) !== false) {
                        $index .= "{% set list=findOneBy('sortable',{'entite':'" . '.$entitie.' . "'}) %}
{% if list != null %}
<input type=\"hidden\" id=\"ex_sortable\" value=\"{{list.Ordre}}\">
{% endif %}
    ";
                    }
                }
                //ajout de la table en responsive
                $index .= "
<div class='table-responsive'>
    <table class='table'>
        <thead>
            <tr>";

                //on boucle sur les fields
                foreach ($res as $field => $val) {
                    // on ajoute les entête
                    $entete = "<th>";
                    //on prend le label ou le champ
                    if (isset($val['OPT']['label'])) {
                        $entete .= ucfirst(stripslashes(substr($val['OPT']['label'], 1, -1)));
                    } else {
                        $entete .= ucfirst($field);
                    }
                    $entete .= "</th>";
                    //on vérifie si on doit l'afficher
                    if (isset($val['ATTR'])) {
                        if (array_search('no_index', $val['ATTR']) === false) {
                            $index .= $entete;
                        }
                    } else {
                        $index .= $entete;
                    }
                }
                //on ajoute la colonne actions
                $index .= "
            <th>Actions</th>
";
                //on installe un curseur pour sortable si demandé
                if (isset($res['id']['ATTR'])) {
                    if (array_search('sortable', $res['id']['ATTR']) !== false) {
                        $index .= "<tbody id=\"sortable\" style=\"cursor:move;\" >";
                    } else {
                        $index .= "<tbody>";
                    }
                } else $index .= '
            </tr>
</thead>
<tbody>';
                $index .= '
{% for  ' . $entitie . '  in  ' . strtolower($entitie) . 's  %}
    <tr data-num="{{' . $entitie . '.id }}">';
                //pour ne pas voir superadmin
                //                 $index .= "
                // {% if 'ROLE_SUPER_ADMIN' not in " . strtolower($entitie) . ".roles %}";
                $relations = ['onetoone', 'manytoone', 'onetomany', 'manytomany'];
                foreach ($res as $field => $val) {
                    //gestion des classes spéciales
                    $ligne = ''; //pour mémoriser le retours des spéciaux
                    //recherche de la présence d'un type relation
                    $relationFind = ''; // pour mémoriser le type de relation
                    foreach ($val['AUTRE'] as $value) {
                        if (in_array(strToLower($value), $relations) !== false) {
                            $relationFind = $relations[in_array(strToLower($value), $relations)];
                        }
                    }
                    //si on a une relation
                    if ($relationFind) {
                        $ligne = "\n<td>{% for item in  " . $entitie . "." . $field . " %}{{ item }},{% endfor %}";
                    }
                    //si on à un no_index
                    if (isset($val['ATTR']['no_index'])) {
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
                        $ligne = "\n<td>{% set options={" . $resChoices . "} %}";
                        $ligne .= "
                    {% set res=[] %}
                    {% for key,option in options %}
                    {% if option in " . $entitie . "." . $field . " %}
                    {% set res=res|merge([key]) %}
                    {% endif %}
                    {% endfor %}
                    {{res|json_encode";
                    }

                    //si on est pas dans un cas ci-dessus
                    if (!$ligne) {
                        $ligne = "\n<td>";
                        //si on a des ALIAS
                        if (isset($val['ALIAS'])) {
                            //on commence par ALIAS fichier
                            if ($val['ALIAS'] == 'fichier') {
                                //on cherche si on a un type de fichier
                                $typeFichier = 'texte'; // valeur par défaut pour fichier
                                foreach ($val['ATTR'] as $attribu) {
                                    $tabFichier = explode('=>', $attribu);
                                    if ($tabFichier[0] == 'image' || $tabFichier[0] == 'icone') $typeFichier = $tabFichier[0];
                                }
                                //si on est du type image ou icone
                                if ($typeFichier !== 'texte') {
                                    $ligne .= "{%if " . $entitie . "." . $field . " %}" .
                                        "<a data-toggle='popover-hover' data-original-title=\"\" title=\"\" data-img=\"{{voir('" . $field . "/'~" . $entitie . "." . $field . ")}}\"><img src=\"{{getico('" . $field . "/'~" . $entitie . "." . $field . ")}}\"></a> {% endif %}";
                                } else
                                    $ligne .= '<label class="exNomFichier">' . "{{" . $entitie . "." . $field . "}}</label>";
                            } else  //si c'est un autre ALIAS
                                $ligne .= '{{' . $entitie . '.' . $field;
                        } else  //si c'est un autre ALIAS
                            $ligne .= '{{' . $entitie . '.' . $field;
                    }
                    //gestion des filtres à ajouter
                    //on a des filtres
                    $filtres = '';
                    if (isset($val['TWIG']))
                        foreach ($val['TWIG'] as $twig) {
                            $filtres .= "|" . $twig;
                        }
                    //on vérifie s'il faut l'afficher (pas de no_index et pas du type relation
                    if (!isset($val['ATTR']['no_index']) && !$relationFind) {
                        $index .= $ligne . $filtres;
                        //pour le type ckeditor on ajoute un filtre
                        if (isset($val['ALIAS']))
                            if ($val['ALIAS'] == 'ckeditor')
                                $index .= '|striptags|u.truncate(200, "...", false)|cleanhtml';
                        //on ferme pour tous les types sauf fichier
                        if (isset($val['ALIAS'])) {
                            if ($val['ALIAS'] != 'fichier') {
                                $index .= "}}";
                            }
                        } else $index .= "}}";

                        //on ferme la ligne pour tous
                        $index .= "</td>";
                        //si c'est une relation
                        if ($relationFind)
                            $index .= $ligne . $filtres . "</td>";
                    }
                }
                //ajout des actions
                $index .= "<td>
                    <form method='post' action=\"{{ path('" . strtolower($entitie) . "_delete', {'id':  $entitie.id }) }}\" 
                    onsubmit=\"return confirm('Etes-vous sûr de vouloir effacer cet item?');\">
                    <div class='row'>
                        <input type=\"hidden\" name=\"_token\" value=\"{{ csrf_token('delete' ~  $entitie . id ) }}\">
                        <a class='btn btn-xs btn-primary' data-toggle='tooltip' title='Voir' href=\"{{ path('" . strtolower($entitie) . "_show', {'id':  $entitie.id }) }}\"><i class=\"icone fas fa-glasses \"></i></a>
                        <a class='btn btn-xs btn-secondary' data-toggle='tooltip' title='Editer' href=\"{{ path('" . strtolower($entitie) . "_edit', {'id':  $entitie.id }) }}\"><i class=\"icone fas fa-pen \"></i></a>
                        <a class='btn btn-xs btn-secondary' data-toggle='tooltip' title='Dupliquer' href=\"{{ path('" . strtolower($entitie) . "_copy',{'id':  $entitie.id }) }}\"><i class=\"icone fas fa-copy \"></i></a>
                        <button class=\"btn btn-xs btn-warning \"><i class=\"icone fas fa-trash \"></i></button>
                    </div>
                        </form>";
                //fermeture de la ligne
                //$index .= "</tr>";
                //fin pour cacher superadmin
                //$index .= "{% endif %}";
                $index .= "
            {% else %}
            <tr>
                <td colspan=" . (count($res) + 1) . ">Aucun enregistrement</td>
            </tr>
            {% endfor %}
            
        </tbody>
    </table>
</div>";

                $index . "<a href=\"{{ path('" . $entitie . "_new') }}\" class=\"btn btn-primary\" type=\"button\">Ajouter</a>";
                //si on est avec sortable 
                if (isset(($res['id'])['ATTR']['sortable']))
                    $index .= "<input entite=\"" . $entitie . "\"  id=\"save_sortable\" type=\"hidden\">";
                //fermeture du block
                $index .= "{% endblock %}";
                if ($input->getOption('origin')) {
                    $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $entitie;
                    @rename('/app/templates/' . strTolower($entitie) . '/index.html.twig', $dir);
                    file_put_contents('/app/templates/' . strTolower($entitie) . '/index.html.twig', $index);
                } else {
                    file_put_contents('/app/crudmick/crud/' . $entitie . '_index.html.twig', $index);
                }

                //creation de show
                $show = "{% extends '"  . $res['id']['EXTEND'] . "' %}";
                $show .= '
{% block title %}  ' . $entitie . ' 
    {% endblock %}
{% block body %} 
<h1> ' . $entitie . ' </h1>';
                //pour ne pas voir superadmin
                //                 $show .= "
                // {% if 'ROLE_SUPER_ADMIN' not in " . strtolower($entitie) . ".roles %}";
                $show .= '
<div class="col-12">
<ul class="list-group">';
                //on boucle sur les fields
                foreach ($res as $field => $val) {
                    //gestion des classes spéciales
                    $ligne = ''; //pour mémoriser le retours des spéciaux
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
                        $ligne = "\n<td>{{" . strtolower($entitie) . "." . $field . "|json_encode";
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
                        $ligne = ucfirst($field) . "{% set options={" . $resChoices . "} %}";
                        $ligne .= "
                    {% set res=[] %}
                    {% for key,option in options %}
                    {% if option in " . strtolower($entitie) . "." . $field . " %}
                    {% set res=res|merge([key]) %}
                    {% endif %}
                    {% endfor %}
                    {{res|json_encode";
                    }
                    //is on est pas dans les cas ci-dessus
                    if (!$ligne) {
                        $ligne = "\n<li class=\"list-group-item\">
        <h6>" . ucfirst($field) . "</h6>\n
        <hr>";
                        //si on a des ALIAS
                        if (isset($val['ALIAS'])) {
                            //on commence par ALIAS fichier
                            if ($val['ALIAS'] == 'fichier') {
                                //on cherche si on a un type de fichier
                                $typeFichier = 'texte'; // valeur par défaut pour fichier
                                foreach ($val['ATTR'] as $attribu) {
                                    $tabFichier = explode('=>', $attribu);
                                    if ($tabFichier[0] == 'image' || $tabFichier[0] == 'icone') {
                                        $typeFichier = $tabFichier[0];
                                    }
                                }
                                //type image
                                if ($typeFichier == 'image') {
                                    $sizef = "";
                                    $size = explode('x', $tabFichier[1]);
                                    if (trim($size[0]) == '0') {
                                        $sizef = 'height=' . $size[1] . 'px';
                                    } else {
                                        $sizef = 'width=' . $size[0] . 'px';
                                    }
                                    $ligne .= "{%if " . strtolower($entitie) . "." . $field . " %}" .
                                        "<a data-toggle='popover-hover' data-original-title=\"\" title=\"\" data-img=\"{{voir('" . $field . "/'~" . strtolower($entitie) . "." . $field . ")}}\"><img " . $sizef . " src=\"{{voir('" . $field . "/'~" . strtolower($entitie) . "." . $field . ")}}\"></a> {% endif %}";
                                }
                                //type icone
                                if ($typeFichier == 'icone') {
                                    $ligne .= "{%if " . strtolower($entitie) . " . " . $field . " %}" .
                                        "<a data-toggle='popover-hover' data-original-title=\"\" title=\"\" data-img=\"{{voir('" . $field . "/'~" . strtolower($entitie) . "." . $field . ")}}\"><img src=\"{{getico('" . $field . "/'~" . strtolower($entitie) . "." . $field . ")}}\"></a> {% endif %}";
                                }
                                //type texte
                                if ($typeFichier == 'texte') {
                                    $ligne .= '<label class="exNomFichier">' . "{{" . strtolower($entitie) . "." . $field . "}}</label>";
                                }
                            } else {   //si c'est un autre ALIAS
                                $ligne .= '{{' . strtolower($entitie) . '.' . $field;
                            }
                        } else {   //si c'est un autre ALIAS
                            $ligne .= '{{' . strtolower($entitie) . '.' . $field;
                        }
                        //gestion des filtres à ajouter
                        //on a des filtres
                        $filtres = '';
                        if (isset($val['TWIG'])) {
                            foreach ($val['TWIG'] as $twig) {
                                $filtres .= "|" . $twig;
                            }
                        }
                        //on vérifie s'il faut l'afficher (pas de no_index et pas du type relation
                        if (!isset($val['ATTR']['no_show'])) {
                            $show .= $ligne . $filtres;
                            //pour le type ckeditor on ajoute un filtre
                            if (isset($val['ALIAS']))
                                if ($val['ALIAS'] == 'ckeditor')
                                    $show .= '|cleanhtml';
                            //on ferme pour tous les types sauf fichier
                            if (isset($val['ALIAS'])) {
                                if ($val['ALIAS'] != 'fichier') {
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

                $show .= "\n" . '<a href="{{ path(\'' . strtolower($entitie) . '_index\') }}" class="btn btn-secondary mr-2" type="button">Revenir à la liste</button></a>';

                $show .= "{% endblock %}";
                if ($input->getOption('origin')) {
                    @mkdir('/app/templates/' . strTolower($entitie));
                    $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $entitie;
                    @mkdir($dir);
                    @rename('/app/templates/' . strTolower($entitie) . '/show.html.twig', $dir);
                    file_put_contents('/app/templates/' . strTolower($entitie) . '/show.html.twig', $show);
                } else {
                    @mkdir('/app/crudmick/crud');
                    file_put_contents('/app/crudmick/crud/' . $entitie . '_show.html.twig', $show);
                }
                //creation du controller
                $controller = "<?php
namespace  App\Controller ;
use App\Entity\\" . $entitie . ';' . '
use App\Form\\' . $entitie . 'Type;
use App\Repository\\' . $entitie . 'Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("' . $res['id']['PARTIE'] . '/' . strTolower($entitie) . '")
 */ class ' . $entitie . 'Controller extends AbstractController
 {
    /**
     * @Route("/", name="' . strTolower($entitie) . '_index", methods={"GET"})
     */
    public function index(' . $entitie . 'Repository $' . strtolower($entitie) . 'Repository): Response
    {
        return $this->render(\'' . strtolower($entitie) . '/index.html.twig\', [
            \'' . strTolower($entitie) . 's\' => $' . strTolower($entitie) . 'Repository->findAll(),
        ]);
    }
      /**
     * @Route("/new", name="' . strTolower($entitie) . '_new", methods={"GET","POST"})
     */
    public function new(Request $request): Response
    {
        $' . strTolower($entitie) . ' = new ' . $entitie . '();
        $form = $this->createForm(' . $entitie . 'Type::class, $' . strTolower($entitie) . ');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($' . strTolower($entitie) . ');
            $entityManager->flush();
                return $this->redirectToRoute(\'' . strTolower($entitie) . '_index\');
        }
        return $this->render(\'' . strTolower($entitie) . '/new.html.twig\', [
            \'' . strTolower($entitie) . '\' => $' . strTolower($entitie) . ',
            \'form\' => $form->createView()
        ]);
    }
     /**
     * @Route("/{id}", name="' . strTolower($entitie) . '_show", methods={"GET"})
     */
    public function show(' . $entitie . ' $' . strTolower($entitie) . '): Response
    {
        return $this->render(\'' . strTolower($entitie) . '/show.html.twig\', [
            \'' . strTolower($entitie) . '\' => $' . strTolower($entitie) . '
        ]);
    }
      /**
     * @Route("/{id}/edit", name="' . strTolower($entitie) . '_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, ' . $entitie . ' $' . strTolower($entitie) . '): Response
    {
        $form = $this->createForm(' . $entitie . 'Type::class, $' . strTolower($entitie) . ');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute(\'' . strTolower($entitie) . '_index\');
        }
        return $this->render(\'' . strTolower($entitie) . '/new.html.twig\', [
            \'' . strTolower($entitie) . '\' => $' . strTolower($entitie) . ',
            \'form\' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/copy", name="' . strTolower($entitie) . '_copy", methods={"GET","POST"})
     */
    public function copy(Request $request, ' . $entitie . ' $' . strTolower($entitie) . 'c): Response
    {
        $' . strTolower($entitie) . ' = clone $' . strTolower($entitie) . 'c;

        $em = $this->getDoctrine()->getManager();
        $em->persist($' . strTolower($entitie) . ');
        $em->flush();
        return $this->redirectToRoute(\'' . strTolower($entitie) . '_index\');
        //$' . strTolower($entitie) . ' = $copier->copy($' . strTolower($entitie) . 'c);
        //$form = $this->createForm(' . $entitie . 'Type::class, $' . strTolower($entitie) . ');
        //$form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($' . strTolower($entitie) . ');
            $entityManager->flush();

            return $this->redirectToRoute(\'' . strTolower($entitie) . '_index\');
        }

        return $this->render(\'' . strTolower($entitie) . '/new.html.twig\', [
            \'' . strTolower($entitie) . '\' => $' . strTolower($entitie) . ',
            \'form\' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="' . strTolower($entitie) . '_delete", methods={"POST"})
     */
    public function delete(Request $request, ' . $entitie . ' $' . strTolower($entitie) . '): Response
    {
        if ($this->isCsrfTokenValid(\'delete\'.$' . strTolower($entitie) . '->getId(), $request->request->get(\'_token\'))) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->remove($' . strTolower($entitie) . ');
            $entityManager->flush();
        }

        return $this->redirectToRoute(\'' . strTolower($entitie) . '_index\');
    }
}
    ';
                if ($input->getOption('origin')) {
                    $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $entitie;
                    @rename('/app/src/Controller/' . $entitie . 'Controller/' . $entitie . 'Controller.php', $dir);
                    file_put_contents('/app/src/Controller/' .  $entitie . 'Controller.php', $controller);
                } else {
                    file_put_contents('/app/crudmick/crud/' . $entitie . 'Controller.php', $controller);
                }

                /* ------------------------------------------------------------------------------------------------------------------ */
                /*                                                                                             GENERATION DU FORMTYPE */
                /* ------------------------------------------------------------------------------------------------------------------ */
                $relation_use = [];
                $collection_use = [];
                $biblio_use = [];
                $FT = '';
                //on boucle sur les fields
                foreach ($res as $field => $val) {

                    //gestion des classes spéciales
                    $ligne = ''; //pour mémoriser le retours des spéciaux
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
                    $tab_ALIAS = ['fichier' => 'file', 'hidden' => 'hidden', 'radio' => 'radio', 'date' => 'date', 'password' => 'password', 'centimetre' => 'CentiMetre', 'metre' => 'metre', 'prix' => 'money', 'ckeditor' => 'CKEditor',  'texte_propre' => 'text', 'email' => 'email', 'color' => 'color', 'phonefr' => 'tel', 'code_postal' => 'text', 'km' => 'number', 'adeli' => 'number'];
                    if (isset($val['ALIAS']))
                        //si on connait cet alias on met son type dans add et on ajoute le use et on ajoute l'alias dans les attr
                        if (array_key_exists($val['ALIAS'], $tab_ALIAS) !== false) {
                            $TYPE = ucfirst($tab_ALIAS[$val['ALIAS']]) . "Type::class";
                            $resAttr[] = "'data-inputmask' => \"'alias': '" . $val['ALIAS'] . "'\"";
                            if ($tab_ALIAS[$val['ALIAS']] == 'money') $resAttr[] = "'divisor' => 100";
                            //on ajoute le type dans les use si pas existant
                            if (!in_array(ucfirst($tab_ALIAS[$val['ALIAS']]), $biblio_use)) {
                                $biblio_use[] = ucfirst($tab_ALIAS[$val['ALIAS']]);
                            }
                        } else {
                            //sinon on met juste le type dans add
                            $TYPE = $field;
                        }


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
                            //si on a une config par =>
                            if (strpos($opt, '=>') !== false) {
                                $tab = explode('=>', $opt);
                                $resOpt[] = "'$tab[0]'=>$tab[1]";
                            } else {
                                $resOpt[] = "$opt";
                            }
                        }
                    }
                    //on ajoute dans $resOpt si on a un type fichier et required=false (pour pouvoir ne rien changer) pour la conversion
                    if (isset($val['ALIAS'])) if ($val['ALIAS'] == 'fichier') $resOpt[] = "'data_class' => null,'required' => false";

                    //gestion des choices
                    if (isset($val['OPT']))
                        if (in_array('choices', $val['OPT']) !== false) {
                            $TYPE = "ChoiceType::class";
                            if (!in_array('Choice', $biblio_use)) {
                                $biblio_use[] = 'Choice';
                            }
                        }
                    if ($field != 'id') {
                        $FT .= "\n->add('$field',$TYPE,['attr'=>[" . implode(',', $resAttr) . "]";
                        if ($resOpt) {
                            $FT .= "," . implode(',', $resOpt);
                        }
                        $FT .= "])";
                    }
                }
            } //fin de la boucle sur les fields
            $finalft = '<?php
namespace App\Form;

use App\Entity\\' . $entitie . ' ;

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


            $finalft .= 'class ' . $entitie . 'Type extends AbstractType
{
public function buildForm(FormBuilderInterface $builder, array $AtypeOption)
{
$builder';
            $finalft .= $FT . ';}

public function configureOptions(OptionsResolver $resolver)
{
$resolver->setDefaults([
            \'data_class\' => ' . $entitie . '::class,
        ]);
    }
}
';



            if ($input->getOption('origin')) {
                $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $entitie;
                @rename('/app/src/Form/' . $entitie . 'Type.php', $dir);
                file_put_contents('/app/src/Form/' .  $entitie . 'Type.php', $finalft);
            } else {
                file_put_contents('/app/crudmick/crud/' . $entitie . 'Type.php', $finalft);
            }
        } else {
            $io->error('Please get the name of entitie');
        }




        return Command::SUCCESS;
    }
}
