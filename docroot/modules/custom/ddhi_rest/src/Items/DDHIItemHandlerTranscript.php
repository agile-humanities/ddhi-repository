<?php


namespace Drupal\ddhi_rest\Items;


class DDHIItemHandlerTranscript extends DDHIItemHandler {

  public function getData() {
    $data = [
      'title' => $this->node->title->getString(),
      'transcript' => $this->node->body->getValue()
    ];
    return $data;
  }
}
