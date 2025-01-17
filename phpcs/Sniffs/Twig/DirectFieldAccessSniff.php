<?php

declare(strict_types=1);

namespace Drupal\cmc\Sniffs\Twig;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

/**
 * Detects direct field access in Twig templates using node.field_foo pattern.
 */
class DirectFieldAccessSniff implements Sniff {

  /**
   * {@inheritdoc}
   */
  public function register(): array {
    return [T_INLINE_HTML];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr) {
    // Only process .html.twig files.
    $fileExtension = strtolower(substr($phpcsFile->getFilename(), -10));
    if ($fileExtension !== '.html.twig') {
      return;
    }

    $tokens = $phpcsFile->getTokens();
    $content = $tokens[$stackPtr]['content'];

    // Look for node.field_ pattern.
    if (preg_match('/node\.field_[a-z0-9_]+/', $content, $matches)) {
      // @todo 1) Expand this to all possible variations of syntax, and 2) Allow
      // this syntax if the template includes metadata explicitly (or if devs
      // wrote something to tell the sniffer to shut up).
      $error = 'Direct field access using "%s" is not recommended. Try to always render full variables that come from the backend.';
      $data = [$matches[0]];
      $phpcsFile->addWarning($error, $stackPtr, 'DirectFieldAccess', $data);
    }
  }

}
