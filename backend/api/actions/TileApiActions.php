<?php

class TileApiActions implements ApiActionGroupInterface {

    public function register(ApiContext $context, ApiResponder $responder): array {
        return [
            'get_tiles' => static function() use ($context, $responder): void {
                $responder->success(['tiles' => $context->tileService()->getTiles()]);
            },

            'get_tile' => static function() use ($context, $responder): void {
                $id = $context->requireStringParam([
                    $_GET['id'] ?? null,
                    $_POST['id'] ?? null,
                ], 'Tile-ID erforderlich');

                $tile = $context->tileService()->getTile($id);
                if ($tile === null) {
                    $responder->json(['success' => false, 'error' => 'Tile nicht gefunden'], 404);
                    return;
                }

                $responder->success(['tile' => $tile]);
            },

            'save_tile' => static function() use ($context, $responder): void {
                $tileData = $context->readArrayPayload('tile', 'tile');
                if (empty($tileData)) {
                    throw new InvalidArgumentException('Tile-Daten erforderlich');
                }

                $result = $context->tileService()->saveTile($tileData);
                if (!empty($result['success'])) {
                    $result['tiles'] = $context->tileService()->getTiles();
                }

                $responder->result($result);
            },

            'delete_tile' => static function() use ($context, $responder): void {
                $id = $context->requireStringParam([
                    $_POST['id'] ?? null,
                ], 'Tile-ID erforderlich');

                $responder->result($context->tileService()->deleteTile($id));
            },

            'update_positions' => static function() use ($context, $responder): void {
                $positions = $context->readArrayPayload('positions', 'positions');
                $responder->result($context->tileService()->updatePositions($positions));
            },

            'get_tile_types' => static function() use ($context, $responder): void {
                $responder->success([
                    'types' => $context->tileService()->getAvailableTypes(),
                    'typesWithMeta' => $context->tileService()->getAvailableTypesWithMeta(),
                ]);
            },
        ];
    }
}