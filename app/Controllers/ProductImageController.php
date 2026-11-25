<?php

declare(strict_types=1);

namespace Patylook\Controllers;

use DateTimeImmutable;
use Patylook\Contracts\ProductImageServiceInterface;
use Patylook\Contracts\ShowcaseServiceInterface;
use Patylook\Controllers\Support\ProductFormPage;
use Patylook\Core\Request;
use Patylook\Core\Response;
use Patylook\Exceptions\NotFoundException;
use Patylook\Exceptions\ValidationException;
use Patylook\Http\Pager;
use Patylook\Models\Gender;
use Patylook\Http\Responder;
use Patylook\Http\UploadReader;
use Patylook\Support\Str;
use Throwable;

/** Product images (upload, removal, serving) and the showcase gallery. */
final class ProductImageController extends Controller
{
    public function __construct(
        Responder $responder,
        private readonly ProductImageServiceInterface $images,
        private readonly ShowcaseServiceInterface $showcase,
        private readonly ProductFormPage $formPage,
        private readonly UploadReader $uploads,
    ) {
        parent::__construct($responder);
    }

    /**
     * Streams a stored product image. Names are validated by the storage adapter, so only files
     * the application wrote can be reached.
     */
    public function serve(Request $request): Response
    {
        return $this->stream($request);
    }

    /** Same as serve() for the public showcase, but only for images of active products. */
    public function servePublic(Request $request): Response
    {
        if (!$this->showcase->isPublicImage($request->routeParam('folder'), $request->routeParam('file'))) {
            throw new NotFoundException();
        }
        return $this->stream($request);
    }

    private function stream(Request $request): Response
    {
        try {
            $file = $this->images->open($request->routeParam('folder'), $request->routeParam('file'));
        } catch (Throwable) {
            throw new NotFoundException();
        }
        $headers = static fn (Response $r): Response => $r
            ->withHeader('Content-Type', 'image/jpeg')
            ->withHeader('Cache-Control', 'private, no-cache') // revalidated through Last-Modified; the main image can be replaced
            ->withHeader('Last-Modified', $file->modifiedAt->setTimezone(new \DateTimeZone('UTC'))->format('D, d M Y H:i:s') . ' GMT');

        $since = $request->header('If-Modified-Since');
        if ($since !== '') {
            $sinceTime = DateTimeImmutable::createFromFormat('D, d M Y H:i:s \G\M\T', $since, new \DateTimeZone('UTC'));
            if ($sinceTime !== false && $file->modifiedAt->getTimestamp() <= $sinceTime->getTimestamp()) {
                return $headers(new Response(304));
            }
        }
        return $headers(Response::file($file->path, 'image/jpeg', $file->size));
    }

    public function showcase(Request $request): Response
    {
        return $this->showcasePage($request, false);
    }

    /** The showcase without the management menu, open to anyone with the link. */
    public function publicShowcase(Request $request): Response
    {
        return $this->showcasePage($request, true);
    }

    private function showcasePage(Request $request, bool $public): Response
    {
        $q = $request->query('q');
        // Anything but a known gender means "Todos".
        $gender = Gender::tryFrom($request->query('gender'));
        $result = $this->showcase->list($this->pageNumber($request), $q, $gender);
        $page = $this->responder->newPage($request, 'Mostruário', 'showcase');
        $page->data['public'] = $public;
        $page->data['seller'] = $public ? $this->showcase->seller() : null;
        $page->data['items'] = $result->items;
        $page->data['query'] = $q;
        $page->data['gender'] = $gender;
        $page->data['pager'] = Pager::for($request, $result);
        return $this->responder->view('showcase', $page);
    }

    /** Adds carousel images (and optionally replaces the main one) of an existing product. */
    public function upload(Request $request): Response
    {
        $id = $this->routeId($request);
        $errors = new ValidationException();
        $images = $this->uploads->read($request, $errors);
        try {
            $errors->throwIfAny();
            $this->images->add($this->user($request), $id, $images);
        } catch (ValidationException $e) {
            return $this->formPage->renderStored($request, 422, $id, $e->fields());
        }
        return $this->responder->success($request, '/products/' . $id . '/edit', 'Imagens enviadas.');
    }

    public function delete(Request $request): Response
    {
        $id = $this->routeId($request);
        $imageId = Str::toInt($request->routeParam('imageId'));
        if ($imageId === null) {
            throw new NotFoundException();
        }
        $destination = '/products/' . $id . '/edit';
        try {
            $this->images->delete($id, $imageId);
        } catch (Throwable $e) {
            return $this->responder->actionError($request, $destination, $e);
        }
        return $this->responder->success($request, $destination, 'Imagem removida.');
    }
}
