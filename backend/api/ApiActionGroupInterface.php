<?php

interface ApiActionGroupInterface {

    /**
     * @return array<string, callable():void>
     */
    public function register(ApiContext $context, ApiResponder $responder): array;
}