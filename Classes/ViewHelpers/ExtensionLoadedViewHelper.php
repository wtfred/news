<?php

/*
 * This file is part of the "news" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace GeorgRinger\News\ViewHelpers;

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

class ExtensionLoadedViewHelper extends AbstractConditionViewHelper
{
    /**
     * Initialize additional argument
     */
    public function initializeArguments()
    {
        $this->registerArgument('extensionKey', 'string', 'Extension which must be checked', true);
        parent::initializeArguments();
    }

    public static function verdict(array $arguments, RenderingContextInterface $renderingContext)
    {
        return ExtensionManagementUtility::isLoaded($arguments['extensionKey']);
    }
}
