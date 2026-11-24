<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use DateTimeZone;
use Patylook\Contracts\ClockInterface;
use Patylook\Contracts\PricebookServiceInterface;
use Patylook\Contracts\ProductServiceInterface;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Form;
use Patylook\Http\Responder;
use Patylook\Models\Pricebook;

final class PricebookController extends Controller
{
    public function __construct(
        Responder $responder,
        private readonly PricebookServiceInterface $prices,
        private readonly ProductServiceInterface $products,
        private readonly ClockInterface $clock,
        private readonly DateTimeZone $timezone,
    ) {
        parent::__construct($responder);
    }

    public function index(Request $request): Response
    {
        $id = $this->routeId($request);
        return $this->render($request, 200, $id, new Pricebook(validFrom: $this->clock->now()->setTime(0, 0)));
    }

    public function store(Request $request): Response
    {
        $id = $this->routeId($request);
        $form = new Form($request);
        $entry = new Pricebook(
            productId: $id,
            productCost: $form->money('product_cost', 'Custo do produto', true),
            packagingCost: $form->money('packaging_cost', 'Custo de embalagem', false),
            logisticsCost: $form->money('logistics_cost', 'Custo logístico', false),
            otherCosts: $form->money('other_costs', 'Outros custos', false),
            finalPrice: $form->money('final_price', 'Preço final', true),
            validFrom: $form->date('valid_from', 'Início da vigência', $this->timezone),
            validTo: $form->date('valid_to', 'Fim da vigência', $this->timezone),
        );
        try {
            $form->throwIfInvalid();
            $this->prices->add($this->user($request), $entry);
        } catch (ValidationException $e) {
            return $this->render($request, 422, $id, $entry, $e->fields());
        }
        return $this->responder->success($request, '/products/' . $id . '/prices', 'Preço cadastrado.');
    }

    /** @param array<string, string> $errors */
    private function render(Request $request, int $status, int $productId, Pricebook $entry, array $errors = []): Response
    {
        $product = $this->products->get($productId);
        $page = $this->responder->newPage($request, 'Preços — ' . $product->name, 'products');
        $page->errors = $errors;
        $page->data['product'] = $product;
        $page->data['prices'] = $this->prices->list($productId);
        $page->data['form'] = $entry;
        return $this->responder->view('product_prices', $page, $status);
    }
}
