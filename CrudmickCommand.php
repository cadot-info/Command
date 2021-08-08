<?php




namespace App\CMCommand;

use Twig\Environment;
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

The option minimum is:
- EXTEND for the twig extend (example: EXTEND=admin/index.html.twig get extend for the twig new, show, delete)
- PARTIE for firewall (example: PARTIE=admin get add admin in route for the controller )

The fields:
- file: for upload by ajax a simple file 
    - ATTR= for many show, template_... (example: new_text, index_picture...)
        - text: for show text
        - picture: widthxheight, exaple (autox100, 100x300 ...)
        
 
 */
class CrudmickCommand extends Command
{
    protected static $defaultName = 'crudmick:generateCrud';
    protected static $defaultDescription = 'Generate beautify Crud from doctrine entity';
    protected $path = 'src/CMService/tpl/'; //path of tpl
    private $E;
    private $timestamptable;
    private $res;
    private $r;
    private $input;


    // Create a private variable to store the twig environment
    private $twig;

    public function __construct(Environment $twig)
    {
        // Inject it in the constructor and update the value on the class
        $this->twig = $twig;

        parent::__construct();
    }

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
        $E = ucfirst($input->getArgument('entitie'));
        $timestamptable = ['createdAt', 'updatedAt', 'deletedAt'];
        $this->input = $input;
        $this->E = $E;
        $this->timestamptable = $timestamptable;

        //data of entity bu reflection class
        if ($E) {
            if (!file_Exists('/app/src/Entity/' . $E . '.php'))
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

                $this->createNew();

                $res = $this->res;
                /* ------------------------------------------------------------------------------------------------------------------ */
                /*                                                                                                  CREATION DE INDEX */
                /* ------------------------------------------------------------------------------------------------------------------ */
                $index = '{% extends \'' . $res['id']['EXTEND'] . '\' %}';
                $index .= '
{% block title %}  ' . $E . ' 
    {% endblock %}
{% block body %} 
<h1> ' . $E . ' </h1>';
                //on installe sortable si demandé
                if (isset($res['id']['ATTR'])) {
                    if (array_search('sortable', $res['id']['ATTR']) !== false) {
                        $index .= "{% set list=findOneBy('sortable',{'entite':'" . '.$E.' . "'}) %}
{% if list != null %}
<input type=\"hidden\" id=\"ex_sortable\" value=\"{{list.Ordre}}\">
{% endif %}
    ";
                    }
                }
                //ajout de la table en responsive
                $index .= "
<div class=''>
    <table class='table'>
        <thead>
            <tr>";

                //on boucle sur les fields
                foreach ($res as $field => $val) {
                    // on ajoute les entête
                    $entete = "<th >";
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
{% for  ' . $E . '  in  ' . strtolower($E) . 's  %}
    <tr data-num="{{' . $E . '.id }}">';
                //pour ne pas voir superadmin
                //                 $index .= "
                // {% if 'ROLE_SUPER_ADMIN' not in " . strtolower($E) . ".roles %}";
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
                        $ligne = "\n<td>{% for item in  " . $E . "." . $field . " %}{{ item }},{% endfor %}";
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
                    {% if option in " . $E . "." . $field . " %}
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
                                $attrs = '';
                                if (isset($val['ATTR']))
                                    foreach ($val['ATTR'] as $attribu) {
                                        $tabFichier = explode('=>', $attribu);
                                        if ($tabFichier[0] == 'image' || $tabFichier[0] == 'icone') $typeFichier = $tabFichier[0];
                                        else $attrs .= $attribu;
                                    }
                                //si on est du type image ou icone
                                if ($typeFichier !== 'texte') { //icone ou image pour ne pas avoir une grande taille
                                    $ligne .= "{%if " . $E . "." . $field . " %}" .
                                        "<a class='bigpicture' " . $attrs . " href=\"{{asset('/uploads/" . $field . "/'~" . $E . "." . $field . ")}}\">
                                        <img src=\"{{getico('" . $field . "/'~" . $E . "." . $field . ")}}\"></a> {% endif %}";
                                } else
                                    $ligne .= "{%if " . $E . "." . $field . " %}" .
                                        "<a class='bigpicture' " . $attrs . " href=\"{{asset('/uploads/" . $field . "/'~" . $E . "." . $field . ")}}\">" .
                                        '<label class="exNomFichier">' . "{{" . $E . "." . $field . "}}</label></a> {% endif %}";
                            } else  //si c'est un autre ALIAS
                                $ligne .= '{{' . $E . '.' . $field;
                        } else  //si c'est pas un ALIAS
                            $ligne .= '{{' . $E . '.' . $field;
                    }
                    //gestion des filtres à ajouter
                    //on a des filtres
                    $filtres = '';
                    if (isset($val['TWIG']))
                        foreach ($val['TWIG'] as $twig) {
                            $filtres .= "|" . $twig;
                        }
                    //timestamptable
                    if (in_array($field, $timestamptable))
                        $filtres .= ' is empty ? "" :' . $E . ' . ' . $field . '|date("d/m à H:i", "Europe/Paris")';

                    //on vérifie s'il faut l'afficher (pas de no_index et pas du type relation
                    if (!isset($val['ATTR']['no_index']) && !$relationFind) {
                        $index .= $ligne . $filtres;
                        //pour le type ckeditor ou editorjs on ajoute un filtre
                        if (isset($val['ALIAS']))
                            if ($val['ALIAS'] == 'ckeditor' or $val['ALIAS'] == 'editorjs')
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
                    <form method='post' action=\"{{ path('" . strtolower($E) . "_delete', {'id':  $E.id }) }}\" >
                    <div class='row'>
                    <div class='col-3'>
                        <input type=\"hidden\" name=\"_token\" value=\"{{ csrf_token('delete' ~  $E . id ) }}\">
                        <a class='btn btn-xs btn-primary' data-toggle='tooltip' title='Voir' href=\"{{ path('" . strtolower($E) . "_show', {'id':  $E.id }) }}\"><i class=\"icone fas fa-glasses \"></i></a>
                        </div>
                        <div class='col-3'>
                        <a class='btn btn-xs btn-secondary' data-toggle='tooltip' title='Editer' href=\"{{ path('" . strtolower($E) . "_edit', {'id':  $E.id }) }}\"><i class=\"icone fas fa-pen \"></i></a>
                        </div>
                        <div class='col-3'>
                        <a class='btn btn-xs btn-secondary' data-toggle='tooltip' title='Dupliquer' href=\"{{ path('" . strtolower($E) . "_copy',{'id':  $E.id }) }}\"><i class=\"icone fas fa-copy \"></i></a>
                        </div>
                        {% if action=='deleted' %}
										<div class='col-3'>
											<button class=\"btn btn-xs btn-warning \" title=\"restaurer\" name=\"delete_restore\">
												<i class=\"icone fas fa-trash-restore \"></i>
											</button>

											<button class=\"btn btn-xs btn-danger \" title=\"supprimer définitivement\" onclick=\"return confirm('Etes-vous sûr de vouloir effacer cet item?');\" name=\"delete_delete\">

												<i class=\"icone fas fa-trash \"></i>
											</button>
										</div>

									{% else %}
										<div class='col-3'>
											<button class=\"btn btn-xs btn-warning \" title=\"mettre dans la corbeille\" name=\"delete_softdelete\">
												<i class=\"icone fas fa-trash \"></i>
											</button>
										</div>

									{% endif %}
                    </div>
                        </form>
                        </td>
                        </tr>
                        ";
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
</div>
<div class=\"row\">
		<div class=\"col\">
			<a class='btn btn-primary' data-toggle='tooltip' title='ajouter enregistrement' href=\"{{ path('" . strtolower($E) . "_new') }}\">Ajouter un enregistrement</a>
		</div>
		{% if action=='deleted' %}
			<div class=\"col-auto\">
				<a class='text-muted ' href=\"{{ path('" . strtolower($E) . "_index') }}\">voir les enregistrements</a>
			</div>
		{% else %}
			<div class=\"col-auto\">
				<a class='text-muted ' href=\"{{ path('" . strtolower($E) . "_deleted') }}\">voir les enregistrements supprimés</a>
			</div>
		{% endif %}
	</div>
                       
";

                $index . "<a href=\"{{ path('" . $E . "_new') }}\" class=\"btn btn-primary\" type=\"button\">Ajouter</a>";
                //si on est avec sortable 
                if (isset(($res['id'])['ATTR']['sortable']))
                    $index .= "<input entite=\"" . $E . "\"  id=\"save_sortable\" type=\"hidden\">";
                //fermeture du block
                $index .= "{% endblock %}";
                if ($input->getOption('origin')) {
                    $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $E;
                    @rename('/app/templates/' . strTolower($E) . '/index.html.twig', $dir);
                    file_put_contents('/app/templates/' . strTolower($E) . '/index.html.twig', $index);
                } else {
                    file_put_contents('/app/crudmick/crud/' . $E . '_index.html.twig', $index);
                }

                //creation de show
                $show = "{% extends '"  . $res['id']['EXTEND'] . "' %}";
                $show .= '
{% block title %}  ' . $E . ' 
    {% endblock %}
{% block body %} 
<h1> ' . $E . ' </h1>';
                //pour ne pas voir superadmin
                //                 $show .= "
                // {% if 'ROLE_SUPER_ADMIN' not in " . strtolower($E) . ".roles %}";
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
                        $ligne = "\n<td>{{" . strtolower($E) . "." . $field . "|json_encode";
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
                    {% if option in " . strtolower($E) . "." . $field . " %}
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
                                if (isset($val['ATTR']))
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
                                    $ligne .= "{%if " . strtolower($E) . "." . $field . " %}" .
                                        "<a data-toggle='popover-hover' data-original-title=\"\" title=\"\" data-img=\"{{voir('" . $field . "/'~" . strtolower($E) . "." . $field . ")}}\"><img " . $sizef . " src=\"{{voir('" . $field . "/'~" . strtolower($E) . "." . $field . ")}}\"></a> {% endif %}";
                                }
                                //type icone
                                if ($typeFichier == 'icone') {
                                    $ligne .= "{%if " . strtolower($E) . " . " . $field . " %}" .
                                        "<a data-toggle='popover-hover' data-original-title=\"\" title=\"\" data-img=\"{{voir('" . $field . "/'~" . strtolower($E) . "." . $field . ")}}\"><img src=\"{{getico('" . $field . "/'~" . strtolower($E) . "." . $field . ")}}\"></a> {% endif %}";
                                }
                                //type texte
                                if ($typeFichier == 'texte') {
                                    $ligne .= '<label class="exNomFichier">' . "{{" . strtolower($E) . "." . $field . "}}</label>";
                                }
                            } else {   //si c'est un autre ALIAS
                                $ligne .= '{{' . strtolower($E) . '.' . $field;
                            }
                        } else {   //si c'est pas un ALIAS
                            if (in_array($field, $timestamptable))
                                $ligne .= '{{' . strtolower($E) . '.' . $field;
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
                            $filtres .= ' is empty ? "" :' . $E . ' . ' . $field . '|date("d/m à H:i", "Europe/Paris")';

                        //on vérifie s'il faut l'afficher (pas de no_index et pas du type relation
                        if (!isset($val['ATTR']['no_show'])) {
                            $show .= $ligne . $filtres;
                            //pour le type ckeditor on ajoute un filtre
                            if (isset($val['ALIAS']))
                                if ($val['ALIAS'] == 'ckeditor' or $val['ALIAS'] == 'editorjs')
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

                $show .= "\n" . '<a href="{{ path(\'' . strtolower($E) . '_index\') }}" class="btn btn-secondary mr-2" type="button">Revenir à la liste</button></a>';

                $show .= "{% endblock %}";
                if ($input->getOption('origin')) {
                    @mkdir('/app/templates/' . strTolower($E));
                    $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $E;
                    @mkdir($dir);
                    @rename('/app/templates/' . strTolower($E) . '/show.html.twig', $dir);
                    file_put_contents('/app/templates/' . strTolower($E) . '/show.html.twig', $show);
                } else {
                    @mkdir('/app/crudmick/crud');
                    file_put_contents('/app/crudmick/crud/' . $E . '_show.html.twig', $show);
                }
                //creation du controller
                $controller = "<?php
namespace  App\Controller ;
use App\Entity\\" . $E . ';' . '
use App\Form\\' . $E . 'Type;
use App\Repository\\' . $E . 'Repository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use DateTimeImmutable;
use App\CMService\FunctionEntitie;

/**
 * @Route("' . $res['id']['PARTIE'] . '/' . strTolower($E) . '")
 */ class ' . $E . 'Controller extends AbstractController
 {
    /**
     * @Route("/", name="' . strTolower($E) . '_index", methods={"GET"})
     */
    public function index(' . $E . 'Repository $' . strtolower($E) . 'Repository): Response
    {
        return $this->render(\'' . strtolower($E) . '/index.html.twig\', [
            \'' . strTolower($E) . 's\' => $' . strTolower($E) . 'Repository->findBy([\'deletedAt\' => null]),
        ]);
    }
    /**
     * @Route("/deleted", name="' . strTolower($E) . '_deleted", methods={"GET"})
     */
    public function deleted(' . $E . 'Repository $' . strtolower($E) . 'Repository, EntityManagerInterface $em): Response
    {
        $tab' . $E . 's = [];
        foreach ($' . \strtolower($E) . 'Repository->findAll() as $' . \strtolower($E) . ') {
            if ($' . \strtolower($E) . '->getDeletedAt() != null) $tab' . $E . 's[] = $' . \strtolower($E) . ';
        }
        return $this->render(\'' . strtolower($E) . '/index.html.twig\', [
            \'' . strTolower($E) . 's\' =>$tab' . $E . 's 
        ]);
    }';
                //boucle pour savoir récupéré les alias autcomplete
                $render = '';
                foreach ($res as $field => $val) {
                    if (isset($val['ALIAS'])) {
                        //on commence par ALIAS autocomplte
                        if ($val['ALIAS'] == 'autocomplete') {
                            $render .= '  \'autocomplete' . ucfirst($field) . '\' => $functionEntitie->getAllOfFields(\'' . \strtolower($E) . '\', \'' . $field . '\'),';
                        }
                    }
                }

                $controller .= '  /**
     * @Route("/new", name="' . strTolower($E) . '_new", methods={"GET","POST"})
     */
    public function new(Request $request';
                if ($render) $controller .= ',FunctionEntitie $functionEntitie';
                $controller .= '): Response
    {
        $' . strTolower($E) . ' = new ' . $E . '();
        $form = $this->createForm(' . $E . 'Type::class, $' . strTolower($E) . ');
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($' . strTolower($E) . ');
            $entityManager->flush();
                return $this->redirectToRoute(\'' . strTolower($E) . '_index\');
        }
        return $this->render(\'' . strTolower($E) . '/new.html.twig\', [
            ' . $render . '
            \'' . strTolower($E) . '\' => $' . strTolower($E) . ',
            \'form\' => $form->createView()
        ]);
    }
     /**
     * @Route("/{id}", name="' . strTolower($E) . '_show", methods={"GET"})
     */
    public function show(' . $E . ' $' . strTolower($E) . '): Response
    {
        return $this->render(\'' . strTolower($E) . '/show.html.twig\', [
            \'' . strTolower($E) . '\' => $' . strTolower($E) . '
        ]);
    }
      /**
     * @Route("/{id}/edit", name="' . strTolower($E) . '_edit", methods={"GET","POST"})
     */
    public function edit(Request $request, ' . $E . ' $' . strTolower($E);
                if ($render) $controller .= ',FunctionEntitie $functionEntitie';
                $controller .= '): Response
    {
        $form = $this->createForm(' . $E . 'Type::class, $' . strTolower($E) . ');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->getDoctrine()->getManager()->flush();
            return $this->redirectToRoute(\'' . strTolower($E) . '_index\');
        }
        return $this->render(\'' . strTolower($E) . '/new.html.twig\', [
             ' . $render . '
            \'' . strTolower($E) . '\' => $' . strTolower($E) . ',
            \'form\' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}/copy", name="' . strTolower($E) . '_copy", methods={"GET","POST"})
     */
    public function copy(Request $request, ' . $E . ' $' . strTolower($E) . 'c): Response
    {
        $' . strTolower($E) . ' = clone $' . strTolower($E) . 'c;

        $em = $this->getDoctrine()->getManager();
        $em->persist($' . strTolower($E) . ');
        $em->flush();
        return $this->redirectToRoute(\'' . strTolower($E) . '_index\');
        //$' . strTolower($E) . ' = $copier->copy($' . strTolower($E) . 'c);
        //$form = $this->createForm(' . $E . 'Type::class, $' . strTolower($E) . ');
        //$form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager = $this->getDoctrine()->getManager();
            $entityManager->persist($' . strTolower($E) . ');
            $entityManager->flush();

            return $this->redirectToRoute(\'' . strTolower($E) . '_index\');
        }

        return $this->render(\'' . strTolower($E) . '/new.html.twig\', [
            \'' . strTolower($E) . '\' => $' . strTolower($E) . ',
            \'form\' => $form->createView(),
        ]);
    }

    /**
     * @Route("/{id}", name="' . strTolower($E) . '_delete", methods={"POST"})
     */
    public function delete(Request $request, ' . $E . ' $' . strTolower($E) . '): Response
    {
        if ($this->isCsrfTokenValid(\'delete\'.$' . strTolower($E) . '->getId(), $request->request->get(\'_token\'))) {
            $entityManager = $this->getDoctrine()->getManager();
              if ($request->request->has(\'delete_delete\'))
                    $entityManager->remove($' . strTolower($E) . ');
                    if ($request->request->has(\'delete_restore\'))
                    $' . strTolower($E) . '->setDeletedAt(null);
                    if ($request->request->has(\'delete_softdelete\'))
                    $' . strTolower($E) . '->setDeletedAt(new DateTimeImmutable(\'now\'));
            $entityManager->flush();
        }
 if ($request->request->has(\'delete_softdelete\'))
                  return $this->redirectToRoute(\'' . strTolower($E) . '_index\');
                else
                  return $this->redirectToRoute(\'' . strTolower($E) . '_deleted\');
    }
}
    ';

                if ($input->getOption('origin')) {
                    $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $E;
                    @rename('/app/src/Controller/' . $E . 'Controller/' . $E . 'Controller.php', $dir);
                    file_put_contents('/app/src/Controller/' .  $E . 'Controller.php', $controller);
                } else {
                    file_put_contents('/app/crudmick/crud/' . $E . 'Controller.php', $controller);
                }

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
                    $tab_ALIAS = ['fichier' => 'file', 'hidden' => 'hidden', 'radio' => 'radio', 'date' => 'date', 'password' => 'password', 'centimetre' => 'CentiMetre', 'metre' => 'metre', 'prix' => 'money', 'autocomplete' => 'text', 'ckeditor' => 'CKEditor', 'editorjs' => 'hidden',  'texte_propre' => 'text', 'email' => 'email', 'color' => 'color', 'phonefr' => 'tel', 'code_postal' => 'text', 'km' => 'number', 'adeli' => 'number'];
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

                    //on ajoute dans $resOpt si on a un type fichier et required=false (pour pouvoir ne rien changer) pour l'envoie du formulaire
                    if (isset($val['ALIAS'])) if ($val['ALIAS'] == 'fichier') $resOpt[] = "'data_class' => null,'required' => false";

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

use App\Entity\\' . $E . ' ;

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


            $finalft .= 'class ' . $E . 'Type extends AbstractType
{
public function buildForm(FormBuilderInterface $builder, array $AtypeOption)
{
$builder';
            $finalft .= $FT . ';}

public function configureOptions(OptionsResolver $resolver)
{
$resolver->setDefaults([
            \'data_class\' => ' . $E . '::class,
        ]);
    }
}
';



            if ($input->getOption('origin')) {
                $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $E;
                @rename('/app/src/Form/' . $E . 'Type.php', $dir);
                file_put_contents('/app/src/Form/' .  $E . 'Type.php', $finalft);
            } else {
                file_put_contents('/app/crudmick/crud/' . $E . 'Type.php', $finalft);
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
        $class = 'App\Entity\\' . $this->E;
        $r = new \ReflectionClass(new $class); //property of class
        //array of search
        $aSupprimer = array('/**', '*/'); // for cleaning
        $mCrud = array('pour éviter retour false', 'ATTR', 'PARTIE', 'EXTEND', 'OPT', 'TPL', 'TWIG', 'ALIAS'); // array for create
        $FUnique = array('EXTEND', 'PARTIE', 'ALIAS'); //array with a uniq value
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

    private function createNew()
    {
        $res = $this->res;
        $E = $this->E;
        $e = strToLower($E);
        $timestamptable = $this->timestamptable;
        $html = $this->twigParser(file_get_contents($this->path . 'new.html.twig'), array('e' => $e, 'E' => $E, 'extends' => $res['id']['EXTEND']));
        $twigNew = []; // array for stock parser
        $twigNew['form_rows'] = ''; //contains string for replace form_rows
        //lopp on fields
        $new = ''; //!!!!!!!!!!!!!!!!!!!!!!
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
                                    $twigNew['form_rows'] .= "\n" . "{%if $e.$field %}\n<img $sizef src=\"{{voir('$field/~$e.$field')}}\">\n{% endif %}"; // add html form
                                    break;
                                case 'new_icon':
                                    $twigNew['form_rows'] .= "\n" . "{%if $e.$field %}\n<img src=\"{{getico('$field/$e.$field')}}\"> \n{% endif %}"; // add html form
                                    break;
                                case 'new_text':
                                default:
                                    $twigNew['form_rows'] .= "\n<label class='exNomFichier'>{{ $e.$field }}</label>";
                                    break;
                            }
                            $twigNew['form_rows'] .= "\n</div>\n";
                        }
                        //for editorjs
                        if ($val['ALIAS'] == 'editorjs')  $twigNew['form_rows'] .= "<div id='editorjs'></div>";
                        //for autocomplete.js
                        if ($val['ALIAS'] == 'autocomplete')
                            $twigNew['form_rows'] .= "<input type='hidden' class='autocomplete' data-id='$e" . "_" . "$field' value='{{autocomplete$Field}}'>";
                    }
                }
            }
        }
        $html = $this->twigParser($html, $twigNew);
        if ($this->input->getOption('origin')) {
            @mkdir('/app/templates/' . $e);
            $dir = "/app/old/" .  date('Y-m-d_H-i-s') . '/' . $E;
            @mkdir($dir);
            @rename('/app/templates/' . $e . '/new.html.twig', $dir);
            file_put_contents('/app/templates/' . $e . '/new.html.twig', $html);
        } else {
            @mkdir('/app/crudmick/crud');
            file_put_contents('/app/crudmick/crud/' . $E . '_new.html.twig', $html);
        }



        dd($html);
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
            $html = str_replace('¤' . $key . '¤', $value, $html);
        }
        return $html;
    }
}
