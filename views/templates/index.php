<?php
/**
 * @author    АО Райффайзенбанк <ecom@raiffeisen.ru>
 * @copyright 2007 АО Райффайзенбанк
 * @license   https://www.gnu.org/licenses/old-licenses/gpl-2.0.txt The GNU General Public License version 2 (GPLv2)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');

header('Location: ../../../../');

exit;
