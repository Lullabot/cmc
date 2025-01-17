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
      $error = 'Direct field access using "%s" is not recommended. Use field.html.twig templates or content variable instead';
      $data = [$matches[0]];
      $phpcsFile->addWarning($error, $stackPtr, 'DirectFieldAccess', $data);
    }
  }

} 