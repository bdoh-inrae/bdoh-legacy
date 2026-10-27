<?php

namespace Irstea\BdohTheiaOzcarBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Irstea\BdohDataBundle\Entity\Observatoire;
use Irstea\BdohDataBundle\Entity\Repository\ObservatoireRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Routing\RouterInterface;

class EnvoiJSON
{
    /** @var string */
    private $uriTheiaSend;

    /** @var PasswordEncryptionServiceInterface */
    private $passwordEncryptionService;

    /** @var RouterInterface */
    private $router;

    /** @var SerializerJSON */
    private $serializerJson;

    /** @var SchemaJSON */
    private $schemaJSON;

    /** @var ZipJSON */
    private $zipJSON;

    /**
     * @var ManagerRegistry
     */
    public $doctrine;

    /**
     * SendJSONToTheiaCommand constructor.
     *
     * @param string $uriTheiaSend
     */
    public function __construct(
        string $uriTheiaSend,
        PasswordEncryptionServiceInterface $passwordEncryptionService,
        RouterInterface $router,
        SerializerJSON $serializerJson,
        SchemaJSON $schemaJSON,
        ZipJSON $zipJSON,
        ManagerRegistry $doctrine
    ) {
        $this->uriTheiaSend = $uriTheiaSend;
        $this->passwordEncryptionService = $passwordEncryptionService;
        $this->router = $router;
        $this->serializerJson = $serializerJson;
        $this->schemaJSON = $schemaJSON;
        $this->zipJSON = $zipJSON;
        $this->doctrine = $doctrine;
    }

    public function sendJsonTheia(string $theiaCode)
    {
        $envoi = $this->doctrine
            ->getManagerForClass(Observatoire::class)
            ->wrapInTransaction(
                function (EntityManagerInterface $manager) use ($theiaCode) {
                    //pour les messages d'erreurs
                    $key = null;
                    $message = null;
                    $responseBody = null;
                    $nameZip = 'BDOH.zip';

                    /** @var ObservatoireRepository $observatoireRepo */
                    $observatoireRepo = $manager->getRepository(Observatoire::class);

                    //récupérer l'bservatoire via son codeTheia
                    $observatoire = $observatoireRepo->findOneBy(['theiaCode' => $theiaCode]);
                    $username = $observatoire->getTheiaCode();
                    $password = $observatoire->getTheiaPassword();

                    //décrypter le mot de passe
                    $passwordDecrypt = $this->passwordEncryptionService->decrypt($password);

                    //ajout du _observatoire pour les routes
                    $slug = $observatoire->getSlug();
                    $url = $this->router->getContext()->setParameter('_observatoire', $slug);

                    //serialization du json avec le mapper theia
                    $seri = $this->serializerJson->serialize($observatoire);
                    $data = json_decode($seri);

                    //vérification du schema de valisation du json
                    $result = $this->schemaJSON->validateSchemaTheia($data);

                    if ($result->isValid()) {
                        // Compression dans un zip du fichier json
                        $filename = tempnam('/tmp', 'theia');

                        $this->zipJSON->zipArchive($seri, $filename, $username);

                        $content = file_get_contents($filename);
                        unlink($filename);
                        $uriTheia = $this->uriTheiaSend . $username . '/new/' . $nameZip;
                        //Envoie du zip à Theia
                        $client = new Client();

                        try {
                            $response = $client->request(
                                'PUT',
                                $uriTheia,
                                [
                                    'headers' => [
                                        'Content-Type' => 'application/zip',
                                    ],
                                    'body'         => $content,
                                    'auth'         => [$username, $passwordDecrypt],
                                ]
                            );
                            $responseBody = $response->getBody()->getContents();
                            $unix = 0;
                        } catch (GuzzleException $e) {
                            // si l'envoie ne fonctionne pas
                            $message = $e->getMessage();
                            // sans réponse quand le serveur ne répond pas (Guzzle 7 ne la propose plus)
                            $response = $e instanceof RequestException ? $e->getResponse() : null;
                            $responseBody = $response?->getBody()->getContents();
                            // passage de la mise à jour en false
                            $observatoire->setMiseajourAutomatique(false);

                            $unix = 1;
                        }
                    } else {
                        // si le json n'est pas valid affichage des erreurs dans la console

                        foreach ($result->getErrors() as $erreurs) {
                            $key = $erreurs->keyword();
                        }
                        // passage de la mise à jour en false

                        $observatoire->setMiseajourAutomatique(false);

                        $unix = 1;
                    }//end result is valid

                    return [$unix, $key, $message, $responseBody, $result, $seri];
                }
            );

        return $envoi;
    }
}
