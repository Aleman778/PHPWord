<?php
/**
 * This file is part of PHPWord - A pure PHP library for reading and writing
 * word processing documents.
 *
 * PHPWord is free software distributed under the terms of the GNU Lesser
 * General Public License version 3 as published by the Free Software Foundation.
 *
 * For the full copyright and license information, please read the LICENSE
 * file that was distributed with this source code. For the full list of
 * contributors, visit https://github.com/PHPOffice/PHPWord/contributors.
 *
 * @see         https://github.com/PHPOffice/PHPWord
 * @copyright   2010-2018 PHPWord contributors
 * @license     http://www.gnu.org/licenses/lgpl.txt LGPL version 3
 */

namespace PhpOffice\PhpWord\Writer\HTML\Part;

use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Style;
use PhpOffice\PhpWord\Style\Font;
use PhpOffice\PhpWord\Style\Paragraph;
use PhpOffice\PhpWord\Style\Table;
use PhpOffice\PhpWord\Writer\HTML;
use PhpOffice\PhpWord\Writer\HTML\Element\Table as TableStyleWriter;
use PhpOffice\PhpWord\Writer\HTML\Style\Font as FontStyleWriter;
use PhpOffice\PhpWord\Writer\HTML\Style\Generic as GenericStyleWriter;
use PhpOffice\PhpWord\Writer\HTML\Style\Paragraph as ParagraphStyleWriter;

/**
 * RTF head part writer
 *
 * @since 0.11.0
 */
class Head extends AbstractPart
{
    /**
     * Write part
     *
     * @return string
     */
    public function write()
    {
        $docProps = $this->getParentWriter()->getPhpWord()->getDocInfo();
        $propertiesMapping = array(
            'creator'     => 'author',
            'title'       => '',
            'description' => '',
            'subject'     => '',
            'keywords'    => '',
            'category'    => '',
            'company'     => '',
            'manager'     => '',
        );
        $title = $docProps->getTitle();
        $title = ($title != '') ? $title : 'PHPWord';

        $content = '';

        $content .= '<head>' . PHP_EOL;
        $content .= '<meta charset="UTF-8" />' . PHP_EOL;
        $content .= '<title>' . $title . '</title>' . PHP_EOL;
        foreach ($propertiesMapping as $key => $value) {
            $value = ($value == '') ? $key : $value;
            $method = 'get' . $key;
            if ($docProps->$method() != '') {
                $content .= '<meta name="' . $value . '"'
                          . ' content="'
                          . HTML::escapeOrNot($docProps->$method())
                          . '"'
                          . ' />' . PHP_EOL;
            }
        }
        $content .= $this->writeStyles();
        $content .= '</head>' . PHP_EOL;

        return $content;
    }

    /**
     * Get styles
     *
     * @return string
     */
    private function writeStyles()
    {
        $css = '<style>' . PHP_EOL;

        // Default styles
        $astarray = array(
            'font-family' => FontStyleWriter::getFontFamily(Settings::getDefaultFontName(), $this->getParentWriter()->getPhpWord()->getDefaultHtmlGenericFont()),
            'font-size'   => Settings::getDefaultFontSize() . 'pt',
        );
        $hws = $this->getParentWriter()->getPhpWord()->getDefaultHtmlWhiteSpace();
        if ($hws) {
            $astarray['white-space'] = $hws;
        }
        $defaultStyles = array(
            '*'         => $astarray,
            'a.NoteRef' => array(
                'text-decoration' => 'none',
            ),
            'hr' => array(
                'height'     => '1px',
                'padding'    => '0',
                'margin'     => '1em 0',
                'border'     => '0',
                'border-top' => '1px solid #CCC',
            ),
            'table' => array(
                'border'         => '0px solid black',
                'border-spacing' => '0px',
                'width '         => '100%',
            ),
            'td' => array(
                'padding-left' => '12px',
                'padding-right' => '12px',
            ),
        );
        foreach ($defaultStyles as $selector => $style) {
            $styleWriter = new GenericStyleWriter($style);
            $css .= $selector . ' {' . $styleWriter->write() . '}' . PHP_EOL;
        }

        
        // Custom styles
        $customStyles = Style::getStyles();
        if (is_array($customStyles)) {
            foreach ($customStyles as $name => $style) {
                $stylep = null;
                if ($style instanceof Font) {
                    $styleWriter = new FontStyleWriter($style);
                    if ($style->getStyleType() == 'title') {
                        $name = str_replace('Heading_', 'h', $name);
                        $stylep = $style->getParagraph();
                        $style = $stylep;
                    } else {
                        $name = '.' . $name;
                    }
                    $css .= "{$name} {" . $styleWriter->write() . '}' . PHP_EOL;
                }

                if ($style instanceof Paragraph) {
                    $styleWriter = new ParagraphStyleWriter($style);
                    if (!$stylep) {
                        $name = '.' . $name;
                    }
                    if ($name === '.Normal') {
                        $name = "p, $name";
                    }
                    $css .= "{$name} {" . $styleWriter->write() . '}' . PHP_EOL;
                }

                if ($style instanceof Table) {
                    $css .= ".{$name} {" . TableStyleWriter::getTableStyleString($style) . '}' . PHP_EOL;

                    // TODO: this is a ugly hack
                    $insideBorderInsideHCss = TableStyleWriter::getTableBorderStyleString($style, 'getBorderInsideH', 'bottom');
                    if ($insideBorderInsideHCss !== '') {
                        $css .= ".{$name} > tbody > .firstRow > td {" . $insideBorderInsideHCss . '}' . PHP_EOL;
                        $css .= ".{$name} > tbody > .row > td {" . $insideBorderInsideHCss . '}' . PHP_EOL;
                    }
                    $insideBorderInsideVCss = TableStyleWriter::getTableBorderStyleString($style, 'getBorderInsideV', 'right');
                    if ($insideBorderInsideVCss !== '') {
                        $css .= ".{$name} > tbody > tr > .firstCol {" . $insideBorderInsideVCss . '}' . PHP_EOL;
                        $css .= ".{$name} > tbody > tr > .col {" . $insideBorderInsideVCss . '}' . PHP_EOL;
                    }

                    $condStyles = $style->getConditionalStyles();
                    if ($condStyles !== null) {
                        foreach ($condStyles as $condType => $condStyle) {
                            if ($condType === 'firstRow' || $condType === 'lastRow' ||
                                $condType === 'band1Horz' || $condType === 'band2Horz') {
                              
                                $css .= ".{$name} > tbody > .{$condType} > td {" .
                                     TableStyleWriter::getTableStyleString($condStyle) . '}' . PHP_EOL;
                            } else if ($condType === 'firstCol' || $condType === 'lastCol') {
                                $css .= ".{$name} > tbody > tr > .{$condType} {" .
                                     TableStyleWriter::getTableStyleString($condStyle) . '}' . PHP_EOL;
                            }
                        }
                    }
                }
            }
        }
        
        $secno = 0;
        $sections = $this->getParentWriter()->getPhpWord()->getSections();
        $intotwip = \PhpOffice\PhpWord\Shared\Converter::INCH_TO_TWIP;
        $css .= 'body > div + div {page-break-before: always;}' . PHP_EOL;
        $css .= 'div > *:first-child {page-break-before: auto;}' . PHP_EOL;
        foreach ($sections as $section) {
            $secno++;
            $secstyl = $section->getStyle();
            $css .= "@page page$secno {";
            $ps = $secstyl->getPaperSize();
            $or = $secstyl->getOrientation();
            if ($this->getParentWriter()->isPdf()) {
                if ($or === 'landscape') {
                    $ps .= '-L';
                }
                $css .= "sheet-size: $ps; ";
            } else {
                $css .= "size: $ps $or; ";
            }
            $css .= 'margin-right: ' . (string) ($secstyl->getMarginRight() / $intotwip) . 'in; ';
            $css .= 'margin-left: ' . (string) ($secstyl->getMarginLeft() / $intotwip) . 'in; ';
            $css .= 'margin-top: ' . (string) ($secstyl->getMarginTop() / $intotwip) . 'in; ';
            $css .= 'margin-bottom: ' . (string) ($secstyl->getMarginBottom() / $intotwip) . 'in; ';
            $css .= '}' . PHP_EOL;
        }
        $css .= '</style>' . PHP_EOL;

        return $css;
    }
}
