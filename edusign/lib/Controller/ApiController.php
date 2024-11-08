<?php

namespace OCA\Edusign\Controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IAppConfig;
use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\IUserManager;
use Psr\Log\LoggerInterface;

class ApiController extends Controller
{
  private ?string $userId;
  private Client $client;
  private LoggerInterface $logger;
  private IRootFolder $rootFolder;
  private IURLGenerator $urlGenerator;
  private IAppConfig $config;
  private IUserManager $userManager;

  public function __construct(
    ?string $userId,
    string $appName,
    IRequest $request,
    LoggerInterface $logger,
    IRootFolder $rootFolder,
    IURLGenerator $urlGenerator,
    IAppConfig $config,
    IUserManager $userManager,
  ) {
    parent::__construct($appName, $request);
    $this->appName = $appName;
    $this->client = new Client();
    $this->config = $config;
    $this->logger = $logger;
    $this->rootFolder = $rootFolder;
    $this->urlGenerator = $urlGenerator;
    $this->userId = $userId;
    $this->userManager = $userManager;
  }

  private function generate_uuid()
  {
    $b = random_bytes(16);
    $b[6] = chr(ord($b[6]) & 0x0f | 0x40);
    $b[8] = chr(ord($b[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
  }
  private function deleteAppValue(string $key): void
  {
    $this->config->deleteKey($this->appName, $key);
  }
  private function getAppValue(string $key): string
  {
    return $this->config->getValueString($this->appName, $key);
  }

  private function setAppValue(string $key, string $value): void
  {
    $this->config->setValueString($this->appName, $key, $value);
  }

  private function getPersonalData(string $uid, string $return_url): array
  {
    $user = $this->userManager->get($uid);
    $display_name = $user->getDisplayName($uid);
    $mail = $user->getEMailAddress();
    $personal_data = (array) $this->query()->getData();
    unset($personal_data["edusign_endpoint"]);
    $personal_data["eppn"] = $uid;
    $personal_data["display_name"] = $display_name;
    $personal_data["mail"] = array($mail);
    $personal_data["return_url"] = $return_url;

    return $personal_data;
  }


  /**
   * @NoCSRFRequired
   * @NoAdminRequired
   *
   * @return DataResponse
   **/
  public function query(): DataResponse
  {
    $response = array(
      "assurance" => array($this->getAppValue("assurance")),
      "authn_context" => $this->getAppValue("authn_context"),
      "edusign_endpoint" => $this->getAppValue('edusign_endpoint'),
      "idp" => $this->getAppValue("idp"),
      "organization" => $this->getAppValue("organization"),
      "registration_authority" => $this->getAppValue("registration_authority"),
      "saml_attr_schema" => $this->getAppValue("saml_attr_schema"),
    );
    return new DataResponse($response);
  }
  /**
   * @NoCSRFRequired
   *
   * @return DataResponse
   **/
  public function register(): DataResponse
  {
    $params = $this->request->getParams();
    $assurance = $params['assurance'];
    $authn_context = $params['authn_context'];
    $edusign_endpoint = $params['edusign_endpoint'];
    $idp = $params['idp'];
    $organization = $params['organization'];
    $registration_authority = $params['registration_authority'];
    $saml_attr_schema = $params['saml_attr_schema'];
    $this->setAppValue("assurance", $assurance);
    $this->setAppValue("authn_context", $authn_context);
    $this->setAppValue("edusign_endpoint", $edusign_endpoint);
    $this->setAppValue("idp", $idp);
    $this->setAppValue("organization", $organization);
    $this->setAppValue("registration_authority", $registration_authority);
    $this->setAppValue("saml_attr_schema", $saml_attr_schema);

    $response = array("status" => "success");
    return new DataResponse($response);
  }
  /**
   * @NoCSRFRequired
   *
   * @return DataResponse
   **/
  public function remove(): DataResponse
  {
    $this->deleteAppValue("idp");
    $this->deleteAppValue("authn_context");
    $this->deleteAppValue("edusign_endpoint");
    $this->deleteAppValue("organization");
    $this->deleteAppValue("assurance");
    $this->deleteAppValue("registration_authority");
    $this->deleteAppValue("saml_attr_schema");
    $response = array("success" => "success");
    return new DataResponse($response);
  }
  /**
   * @NoCSRFRequired
   * @NoAdminRequired
   * @return JSONResponse
   **/
  public function request(): JSONResponse
  {
    $params = $this->request->getParams();
    $path = $params['path'];
    $redirect_uri = $params['redirect_uri'];
    $return_url = $this->urlGenerator->getAbsoluteURL("/index.php/apps/edusign/response");
    $error_response = array("error" => true);
    if (!$this->userId) {
      $error_response["message"] = "No user logged in";
      return new JSONResponse(json_encode($error_response));
    }
    $userFolder = $this->rootFolder->getUserFolder($this->userId);
    $contents = "";
    $mimetype = "";
    $name = '';
    $size = '';

    // check if file exists and read from it if possible
    try {
      /** @var File $node */
      $node = $userFolder->get($path);
      if ($node->isReadable() && $node->getType() == "file") {
        $contents = $node->getContent();
        $mimetype = $node->getMimeType();
        $size = $node->getSize();
        $name = $node->getName();
      } else {
        $this->logger->error('Can not read file');
        $error_response["message"] = "Could not read file";
        return new JSONResponse(json_encode($error_response));
      }
    } catch (NotFoundException) {
      $this->logger->error('File does not exist');
      $error_response["message"] = "File does not exist";
      return new JSONResponse(json_encode($error_response));
    }

    $edusign_endpoint = $this->getAppValue('edusign_endpoint') . "/create-sign-request";
    $uuid = $this->generate_uuid();
    $this->setAppValue('eduid-path-' . $uuid, $path);
    $this->setAppValue('eduid-redirect-uri-' . $uuid, $redirect_uri);
    $b64pdf = base64_encode($contents);

    $signreq = array(
      "api_key" => "dummy",
      "personal_data" => $this->getPersonalData($this->userId, $return_url),
      "payload" => array(
        "documents" => array(
          "invited" => array(),
          "owned" => array(),
          "local" => array(
            array(
              "name" => $name,
              "size" => $size,
              "type" => $mimetype,
              "key" => $uuid,
              "blob" => "data:" . $mimetype . ";base64," . $b64pdf
            )
          )
        )
      )
    );
    try {
      $response = $this->client->post(
        $edusign_endpoint,
        ['json' => $signreq]
      );
    } catch (RequestException $e) {
      $this->logger->error($e->getMessage());
      $error_response["message"] = "RequestException";
      return new JSONResponse(json_encode($error_response));
    }

    $body = $response->getBody();
    $string_body = "";
    if ($body) {
      $string_body = $body->getContents();
      $array_body = json_decode($string_body);
      $payload = $array_body->payload;
      if (!$array_body->error) {
        $this->setAppValue('eduid-uid-' . $payload->relay_state, $this->userId);
      } else {
        $this->logger->error($array_body->message);
        $error_response["message"] = $array_body->message;
        return new JSONResponse(json_encode($error_response));
      }
    } else {
      $this->logger->error("No response body");
      $error_response["message"] = "No response body";
      return new JSONResponse(json_encode($error_response));
    }

    return new JSONResponse($string_body);
  }
  /**
   * @NoCSRFRequired
   * @PublicPage
   * @return RedirectResponse
   **/
  public function response(): RedirectResponse
  {
    $edusign_endpoint = $this->getAppValue('edusign_endpoint') . "/get-signed";
    $params = $this->request->getParams();
    $relay_state = $params['RelayState'];
    $sign_response = $params['EidSignResponse'];
    $redirect_uri = $this->urlGenerator->getBaseUrl();
    $return_url = $this->urlGenerator->getAbsoluteURL("/index.php/apps/edusign/response");
    $uid = $this->getAppValue('eduid-uid-' . $relay_state);
    $docreq = array(
      "api_key" => "dummy",
      "personal_data" => $this->getPersonalData($uid, $return_url),
      "payload" => array(
        "sign_response" => $sign_response,
        "relay_state" => $relay_state
      )
    );
    try {
      $response = $this->client->post(
        $edusign_endpoint,
        ['json' => $docreq]
      );
      $body = $response->getBody();
      if ($body) {
        $string_body = $body->getContents();
        $array_body = json_decode($string_body);
        if (!$array_body->error) {
          $payload = $array_body->payload;
          // We only have one document in the response, so we can get it directly
          $document = $payload->documents[0];
          $uuid = $document->id;
          $redirect_uri = $this->getAppValue('eduid-redirect-uri-' . $uuid);
          $path = $this->getAppValue('eduid-path-' . $uuid);
          $info = pathinfo($path);
          $filenamebase = $info['filename'] . "-signed";
          $filename = $filenamebase . "." . $info['extension'];
          $filecontent = base64_decode((string)$document->signed_content);
          $userFolder = $this->rootFolder->getUserFolder($uid);
          $index = 1;
          while ($userFolder->nodeExists($filename)) {
            $filename = $filenamebase . "-{$index}." . $info['extension'];
            $index++;
          }
          $this->logger->debug("Creating file {$filename}");
          try {
            $filehandle = $userFolder->newFile($filename);
            $filehandle->putContent($filecontent);
          } catch (NotPermittedException $e) {
            $this->logger->error($e->getMessage());
          }

          $this->deleteAppValue('eduid-redirect-uri-' . $uuid);
          $this->deleteAppValue('eduid-path-' . $uuid);
          $this->deleteAppValue('eduid-uid-' . $relay_state);
        }
      } else {
        $this->logger->error("Error: {$response->getStatusCode()}");
      }
    } catch (RequestException $e) {
      $this->logger->error($e->getMessage());
    }
    return new RedirectResponse($redirect_uri, Http::STATUS_OK);
  }
}
