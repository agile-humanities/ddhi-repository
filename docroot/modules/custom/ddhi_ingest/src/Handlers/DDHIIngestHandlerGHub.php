 <?php

 /**
  * @file
  * Contains \Drupal\ddhi_ingest\Handler\DDHIIngestHandler.
  *
  * Handler for managing the ingestion o TEI interviews from a Github Repository into Drupal.
  */

 abstract class DDHIIngestHandlerGHub extends DDHIIngestHandler {

   public function __construct($sourceType)
   {
     parent::__construct($sourceType);
   }
 }
