<?php

 /**
  * @file
  * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandler.
  *
  * Handler for managing the ingestion of TEI interviews from a Github Repository into Drupal.
  */

namespace Drupal\ddhi_ingest\Handlers;

use Drupal\ddhi_ingest\Handlers\DDHIIngestHandlerFile;


class DDHIIngestHandlerGHub extends DDHIIngestHandlerFile {

   public function __construct()
   {
     parent::__construct(DDHI_SOURCE_OPTION_GITHUB);
   }

   public function getGitHubURI() {
     if (count($this->parameters) < 3) {
       throw new \Exception("Warning: GitHub Repository Information is incomplete. Ingestion not performed");
     }

     // GitHub URI is in the following form: https://api.github.com/repos/:owner/:repo/:archive_format/:ref   BAD??
     /// Try: https://github.com/:owner/:repo/archive/:ref.:archive_format

     $archive_format_extension = 'zip';

     $uri = "https://github.com/". $this->parameters['github_account'] .'/'. $this->parameters['github_repository'] ."/archive/". $this->parameters['github_branch'] .".{$archive_format_extension}";
     return $uri;
   }

   public function retrieveSource($uri = null,$filename=null)
   {
     $uri = $uri === null ? $this->getGitHubURI() : $uri;
     $filename = urlencode($this->parameters['github_branch']);
     return parent::retrieveSource($uri,$filename);
   }


}
