<?php

class RenderApiActions implements ApiActionGroupInterface {

    public function register(ApiContext $context, ApiResponder $responder): array {
        return [
            'render_tile_html' => static function() use ($context, $responder): void {
                $tileData = $context->readArrayPayload('tile', 'tile');
                if (empty($tileData) || empty($tileData['type'])) {
                    throw new InvalidArgumentException('Tile-Daten mit type erforderlich');
                }

                $html = $context->generatorService()->renderSingleTile($tileData);
                if ($html === null) {
                    $responder->json(['success' => false, 'error' => 'Unbekannter Tile-Typ'], 400);
                    return;
                }

                $responder->success(['html' => $html]);
            },

            'render_all_tiles_html' => static function() use ($context, $responder): void {
                $responder->success([
                    'contractVersion' => RenderContract::RENDERED_TILE_VERSION,
                    'tiles' => $context->generatorService()->renderAllTilesHtml(),
                ]);
            },

            'render_canvas_layout' => static function() use ($context, $responder): void {
                $responder->success([
                    'contractVersion' => RenderContract::CANVAS_SECTION_VERSION,
                    'sections' => $context->generatorService()->renderCanvasSections(),
                ]);
            },

            'get_canvas_css' => static function() use ($context): void {
                header('Content-Type: text/css; charset=utf-8');
                echo $context->generatorService()->getCanvasCSS();
            },

            'get_canvas_js' => static function() use ($context): void {
                header('Content-Type: application/javascript; charset=utf-8');
                echo $context->generatorService()->getCanvasJS();
            },

            'preview' => static function() use ($context): void {
                header('Content-Type: text/html; charset=utf-8');
                echo $context->generatorService()->preview();
            },
        ];
    }
}