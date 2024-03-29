<?php

namespace App\Controller;

use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Form\Exception\LogicException;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Annotation\Route;

class ScrapingController extends AbstractController
{

    public function getHtmlFromUrl($url)
    {


        $client = HttpClient::create(['headers' => [
		
                'User-Agent'=> 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.14; rv:63.0) Gecko/20100101 Firefox/63.0',
                'Accept'=> 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language'=> 'fr,fr-FR;q=0.8,en-US;q=0.5,en;q=0.3',
                'Accept-Encoding'=> 'gzip, deflate, br',
                'Referer'=> 'https://www.pagesjaunes.fr/',
                'Content-Type'=> 'application/x-www-form-urlencoded',
                'Content-Length'=> '379',
                'Connection'=> 'keep-alive',
                'Upgrade-Insecure-Requests'=> '1',
                'Cache-Control'=> 'max-age=0'
        ]]);
        $response = $client->request('GET', $url);
        return $response->getContent();
    }

    public  function getHtmlCurl($url){

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "postman-token: 65d515b9-0f70-035a-1517-c3129966be83"
            ),
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);
        return $response ;

    }
	
	 private function getErrorMessages(\Symfony\Component\Form\Form $form) {      
    $errors = array();
    foreach ($form->getErrors(true, false) as $error) {
        // My personnal need was to get translatable messages
        // $errors[] = $this->trans($error->current()->getMessage());
        $errors[] = $error->current()->getMessage();
    }

    return $errors;
}
    /**
     * @Route("/", name="scraping")
     */
    public function index(LoggerInterface $logger, Request $request)
    {


$pages  = 0 ;
        $form = $this->createFormBuilder()
            ->add('type', TextType::class, ['label' => "Secteur d’activités", "attr" => ["class" => "input100"]])
            ->add('postal', TextType::class, ['label' => 'Code postal / Département ', "attr" => ["class" => "input100"]]) 
			

            ->add('save', SubmitType::class, ['label' => 'Chercher', "attr" => ["class" => "contact100-form-btn"]]) 
            ->getForm();

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {

	

            $context = stream_context_create(
                array(
                    "http" => array(
                        "header" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36"
                    )
                )
            );


            $data = $form->getData();

            $scraping['postal'] = urlencode($data["postal"]);
            $scraping['type'] = urlencode($data["type"]);

if($scraping['type'] != "" and $scraping['postal'] != "" ){


            $scraping['url'] = "https://www.pagesjaunes.fr/recherche/departement/" . $scraping['postal'] . "/" . $scraping['type'] . "?quoiqui=" . $scraping['type'];
            $datas = [];
            $url = $scraping['url'];
            try {
              // $html = $this->getHtmlFromUrl($url);
                $html = file_get_contents($url, false, $context);

                $crawler = new Crawler($html);
                try {
                    $pages = $this->countPaginationPages($crawler->filter('span.pagination-compteur ')->text());
					
					return $this->redirectToRoute(
                      'import',
                        array('type' => $scraping['type'], 'postal' => $scraping['postal'] , 'pages'=> $pages),
                       );
                } catch (\InvalidArgumentException $m) {
					dump($m->getMessage());die();
                    return $this->render('scraping/index.html.twig', [
                        'form' => $form->createView(),
                        'message' => "Page jaune retourne une erreur",
                    ]);
                }
		   } catch (LogicException $m) {
			   					dump($m->getMessage());die();

                return $this->render('scraping/index.html.twig', [
                    'form' => $form->createView(),
                    'message' => "Page jaune retourne une erreur",
                ]);
            }
        }
		}

        return $this->render('scraping/index.html.twig', [
            'form' => $form->createView(),
            'pages' => $pages,
        ]);


    }
	
    /**
     * @Route("/import/{type}/{postal}/{pages}", name="import")
     */
    public function import( $type ,$postal , $pages ,Request $request  )
    {


            $datas = [];

  $context = stream_context_create(
                array(
                    "http" => array(
                        "header" => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36"
                    )
                )
            );


        


             $pagesPagination = [];
					for( $p= 1 ; $p < ($pages/5) ; $p++){
						$first = ( $p - 1 ) * 5 ;
				$last = $first + 5;
						$pagesPagination["Importez les données de la page " .$first ." à " .$last] = $p ;
					}
					
					
					
					
					 $form = $this->createFormBuilder()
             ->add('type', TextType::class, ['data' => $type ,'label' => "Secteur d’activités", "attr" => ["class" => "input100"]])
            ->add('postal', TextType::class, ['data' =>$postal   , 'label' => 'Code postal / Département ', "attr" => ["class" => "input100"]])
            ->add('page', choiceType::class, [ 'choices' => $pagesPagination,'label' => 'pages', "attr" => ["class" => "input100 form-control"]])
			                    

            ->add('save', SubmitType::class, ['label' => 'Importer', "attr" => ["class" => "contact100-form-btn"]])
            ->getForm();

				
				
				        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
				
				    $data = $form->getData();

            $scraping['postal'] = urlencode($data["postal"]);
            $scraping['type'] = urlencode($data["type"]);
            $scraping['page'] = $data["page"];
		 $scraping['url'] = "https://www.pagesjaunes.fr/recherche/departement/" . $scraping['postal'] . "/" . $scraping['type'] . "?quoiqui=" . $scraping['type'];

				
				$first = ( $scraping['page'] - 1 ) * 5 ;
				$last = $first + 5;
				

				
                $type = $data['type'];
                $postal = $data['postal'];
                for ($j = $first; $j < $last ; $j++) {
                    $url = $scraping['url'];
                    if ($j > 1) $url .= "&page=" . $j;

                    $html = file_get_contents($url, false, $context);

                    //  $html = $this->getHtmlFromUrl($url);

                    $crawler = new Crawler($html);

                    $datas = array_merge($datas, $crawler->filter('li.bi-pro')->each(function (Crawler $node, $i) use ($postal, $type) {

                        $sites = $node->filter('li.bi-site-internet')->each(function (Crawler $node, $s) use ($postal, $type) {
                            return $node->filter('a')->text();

                        });
                        foreach ($sites as $key => $site) {
                            if ($site == "Rejoignez nous sur Facebook") unset($sites[$key]);
                        }

                        return [
                            "type" => $type,
                            "postal" => $postal,
                            "Nom" => $node->filter('h3.noTrad')->filter('a')->text(),
                            "téléphone" => ($node->filter('div.bi-contact-tel')->count() > 0) ? $node->filter('div.bi-contact-tel')->text() : "",
                            "adresse" => $node->filter('a.adresse')->text(),
                            "site" => implode(",", $sites),
                        ];

                    }

                    ));
				

                        usleep(1000000);

                }



                $fp = fopen('php://temp', 'w');
                foreach ($datas as $fields) {
                    fputcsv($fp, $fields, ';');
                }

                rewind($fp);
                $response = new Response(stream_get_contents($fp));
                fclose($fp);

                $response->headers->set('Content-Type', 'text/csv');
                $response->headers->set('charset', 'UTF-8');
                $response->headers->set('Content-Encoding', 'UTF-8');

                $response->setCharset('UTF-8');
                $fileName = $scraping['postal'] . "-" . $scraping['type'] . "-" . $scraping['page'] . ".csv";
                $response->headers->set('Content-Disposition', "attachment; filename=" . $fileName . "");;

                echo "\xEF\xBB\xBF";
                return $response;
				
				
		}
         
  return $this->render('scraping/index.html.twig', [
            'form' => $form->createView(),
            'pages' => $pages,
        ]);
	}

    public function countPaginationPages($pagination)
    {
        return (int)explode("/", $pagination)[1];
    }

    /**
     * @Route("/all", name="search")
     */
    public function getAll()
    {

        $scrapings = [
            [
                "type" => "poseur carrelage",
                "postal" => "13",
                "url" => 'https://www.pagesjaunes.fr/recherche/departement/bouches-du-rhone-13/poseur-carrelage?quoiqui=poseur+carrelage&ou=Bouches-du-Rh%C3%B4ne+%2813%29&univers=pagesjaunes&idOu=D013&ouNbCar=&acOuSollicitee=1&rangOu=2&sourceOu=HISTORIQUE&typeOu=Departement&nbPropositionOuTop=1&nbPropositionOuHisto=1&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage',
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "33",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/gironde-33/poseur-carrelage?quoiqui=poseur+carrelage&ou=33&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=33&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",

            ],
            [
                "type" => "poseur carrelage",
                "postal" => "47",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/lot-et-garonne-47/poseur-carrelage?quoiqui=poseur+carrelage&ou=47&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=47&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",

            ],
            [
                "type" => "poseur carrelage",
                "postal" => "46",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/lot-46/poseur-carrelage?quoiqui=poseur+carrelage&ou=46&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=46&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",

            ],
            [
                "type" => "poseur carrelage",
                "postal" => "12",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/aveyron-12/poseur-carrelage?quoiqui=poseur+carrelage&ou=12&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=12&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "48",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/lozere-48/poseur-carrelage?quoiqui=poseur+carrelage&ou=48&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=48&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",

            ],
            [
                "type" => "poseur carrelage",
                "postal" => "07",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/ardeche-07/poseur-carrelage?quoiqui=poseur+carrelage&ou=07&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=07&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "26",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/drome-26/poseur-carrelage?quoiqui=poseur+carrelage&ou=26&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=26&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "05",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/hautes-alpes-05/poseur-carrelage?quoiqui=poseur+carrelage&ou=05&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=05&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "04",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/alpes-de-haute-provence-04/poseur-carrelage?quoiqui=poseur+carrelage&ou=04&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=04&acQuiQuoiSollicitee=1&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=1&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "06",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/alpes-maritimes-06/poseur-carrelage?quoiqui=poseur+carrelage&ou=06&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=06&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "84",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/vaucluse-84/poseur-carrelage?quoiqui=poseur+carrelage&ou=84&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=84&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "83",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/var-83/poseur-carrelage?quoiqui=poseur+carrelage&ou=83&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=83&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "30",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/gard-30/poseur-carrelage?quoiqui=poseur+carrelage&ou=30&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=30&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "34",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/herault-34/poseur-carrelage?quoiqui=poseur+carrelage&ou=34&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=34&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "82",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/tarn-et-garonne-82/poseur-carrelage?quoiqui=poseur+carrelage&ou=82&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=82&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "81",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/tarn-81/poseur-carrelage?quoiqui=poseur+carrelage&ou=81&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=81&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "32",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/gers-32/poseur-carrelage?quoiqui=poseur+carrelage&ou=32&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=32&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "40",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/landes-40/poseur-carrelage?quoiqui=poseur+carrelage&ou=40&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=40&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "64",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/pyrenees-atlantiques-64/poseur-carrelage?quoiqui=poseur+carrelage&ou=64&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=64&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "65",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/hautes-pyrenees-65/poseur-carrelage?quoiqui=poseur+carrelage&ou=65&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=65&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "32",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/gers-32/poseur-carrelage?quoiqui=poseur+carrelage&ou=32&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=32&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "31",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/haute-garonne-31/poseur-carrelage?quoiqui=poseur+carrelage&ou=31&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=31&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "09",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/ariege-09/poseur-carrelage?quoiqui=poseur+carrelage&ou=09&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=09&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "11",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/aude-11/poseur-carrelage?quoiqui=poseur+carrelage&ou=11&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=11&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],
            [
                "type" => "poseur carrelage",
                "postal" => "66",
                "url" => "https://www.pagesjaunes.fr/recherche/departement/pyrenees-orientales-66/poseur-carrelage?quoiqui=poseur+carrelage&ou=66&univers=pagesjaunes&idOu=&acOuSollicitee=1&nbPropositionOuTop=5&nbPropositionOuHisto=0&ouSaisi=66&acQuiQuoiSollicitee=0&nbPropositionQuiQuoiTop=0&nbPropositionQuiQuoiHisto=0&nbPropositionQuiQuoiGeo=0&quiQuoiSaisi=poseur+carrelage",
            ],

        ];


        $datas = [];
        foreach ($scrapings as $key => $scraping) {

            $url = $scraping['url'];
            $opts = array('http' => array('header' => "User-Agent:MyAgent/1.0\r\n"));
            $context = stream_context_create($opts);
            $html = file_get_contents($url, false, $context);
            $crawler = new Crawler($html);
            $pages = $this->countPaginationPages($crawler->filter('span.pagination-compteur ')->text());
            //  $fp = fopen('php://output', 'w');


            for ($j = 1; $j < $pages + 1; $j++) {
                $url = $scraping['url'];
                if ($j > 1) $url .= "&page=" . $j;
                $opts = array('http' => array('header' => "User-Agent:MyAgent/1.0\r\n"));
                $context = stream_context_create($opts);
                $html = file_get_contents($url, false, $context);
                $crawler = new Crawler($html);
                $postal = $scraping['postal'];
                $datas = array_merge($datas, $crawler->filter('li.bi-pro')->each(function (Crawler $node, $i) use ($postal) {

                    $sites = $node->filter('li.bi-site-internet')->each(function (Crawler $node, $s) use ($postal) {
                        return $node->filter('a')->text();

                    });
                    foreach ($sites as $key => $site) {
                        if ($site == "Rejoignez nous sur Facebook") unset($sites[$key]);
                    }

                    return [
                        "postal" => $postal,
                        "Nom" => $node->filter('h3.noTrad')->filter('a')->text(),
                        "téléphone" => ($node->filter('div.bi-contact-tel')->count() > 0) ? $node->filter('div.bi-contact-tel')->text() : "",
                        "adresse" => $node->filter('a.adresse')->text(),
                        "site" => implode(",", $sites),
                    ];

                }

                ));
            }

        }

        $fp = fopen('php://temp', 'w');
        foreach ($datas as $fields) {
            fputcsv($fp, $fields, ';');
        }

        rewind($fp);
        $response = new Response(stream_get_contents($fp));
        fclose($fp);

        $response->headers->set('Content-Type', 'text/csv');
        $response->headers->set('charset', 'UTF-8');
        $response->headers->set('Content-Encoding', 'UTF-8');

        $response->setCharset('UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="pagesjaune.csv"');
        echo "\xEF\xBB\xBF";
        return $response;
    }
}

