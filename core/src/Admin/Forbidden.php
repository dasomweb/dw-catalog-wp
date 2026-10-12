<?php
namespace DWCatalog\Admin;

/** Thrown for permission / CSRF / license failures; the host turns it into a 403 page. */
class Forbidden extends \RuntimeException {
}
