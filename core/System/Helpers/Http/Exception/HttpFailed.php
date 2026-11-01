<?php

namespace Maws\System\Helpers\Http\Exception;

use Maws\System\Exception\MawsException;
use Maws\System\Helpers\Http\Response;

class HttpFailed extends MawsException
{
    public function __construct(private Response $response)
    {
        parent::__construct(sprintf(
            'Сервер ответил %d %s на %s %s',
            $response->status(),
            $response->reason(),
            $response->method(),
            $response->url()
        ));
    }

    public function response(): Response
    {
        return $this->response;
    }

}
