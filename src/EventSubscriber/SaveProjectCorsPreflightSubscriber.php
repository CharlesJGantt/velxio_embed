<?php

namespace Drupal\velxio_embed\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Answers the CORS preflight for /api/velxio/save-project with real headers.
 *
 * Drupal core's \Drupal\Core\EventSubscriber\OptionsRequestSubscriber
 * intercepts every OPTIONS request site-wide on KernelEvents::REQUEST at
 * priority 1000 -- before routing, before any controller (including
 * SaveProjectController::save()) ever runs -- and replaces it with a bare
 * `200` carrying only an `Allow` header. No CORS headers, so a real browser
 * preflight from the Lab's origin fails and the actual POST never fires.
 * This subscriber runs at a higher priority (so it executes first) and
 * claims only this one path, building the actual CORS preflight response
 * and stopping propagation so core's generic handler never overwrites it.
 * Every other OPTIONS request on this site is untouched.
 */
class SaveProjectCorsPreflightSubscriber implements EventSubscriberInterface {

  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();
    if (!$request->isMethod('OPTIONS') || $request->getPathInfo() !== '/api/velxio/save-project') {
      return;
    }

    $origin = (string) $request->headers->get('Origin', '');
    $allowed = $this->configFactory->get('velxio_embed.settings')->get('save_allowed_origins') ?: [];

    $response = new Response('', 204);
    if ($origin !== '' && in_array($origin, $allowed, TRUE)) {
      $response->headers->set('Access-Control-Allow-Origin', $origin);
      $response->headers->set('Access-Control-Allow-Methods', 'POST, OPTIONS');
      $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
      $response->headers->set('Access-Control-Max-Age', '600');
      $response->headers->set('Vary', 'Origin');
    }

    $event->setResponse($response);
    $event->stopPropagation();
  }

  public static function getSubscribedEvents(): array {
    // Strictly greater than OptionsRequestSubscriber's 1000 so this runs
    // first and can stop propagation before core's generic handler fires.
    $events[KernelEvents::REQUEST][] = ['onRequest', 1001];
    return $events;
  }

}
