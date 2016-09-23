<?php

namespace Tokenpass\Providers\CMSAuth;

use Exception;

class CMSException extends Exception {


    public function setJSONResponse($json_response) {
        $this->json_response = $json_response;
    }

    public function getJSONResponse() {
        return $this->json_response;
    }

}