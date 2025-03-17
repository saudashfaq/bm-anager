<?php
class Pagination
{
    private $totalItems;
    private $itemsPerPage;
    private $currentPage;
    private $baseUrl;

    public function __construct($totalItems, $itemsPerPage, $currentPage, $baseUrl)
    {
        $this->totalItems = (int)$totalItems;
        $this->itemsPerPage = (int)$itemsPerPage;
        $this->currentPage = (int)$currentPage;
        $this->baseUrl = $baseUrl;
    }

    public function getTotalPages()
    {
        return max(1, ceil($this->totalItems / $this->itemsPerPage));
    }

    public function getOffset()
    {
        return ($this->currentPage - 1) * $this->itemsPerPage;
    }

    public function render()
    {
        $totalPages = $this->getTotalPages();
        if ($totalPages <= 1) {
            return '';
        }

        $html = '<nav><ul class="pagination justify-content-center">';

        // Previous button
        $prevDisabled = $this->currentPage <= 1 ? 'disabled' : '';
        $html .= '<li class="page-item ' . $prevDisabled . '">';
        $html .= '<a class="page-link" href="#" data-page="' . max(1, $this->currentPage - 1) . '" ' . ($prevDisabled ? '' : 'aria-label="Previous"') . '>';
        $html .= '<span aria-hidden="true">&laquo;</span></a></li>';

        // Page numbers
        $range = 2; // Show 2 pages before and after the current page
        $start = max(1, $this->currentPage - $range);
        $end = min($totalPages, $this->currentPage + $range);

        // Show first page and ellipsis if needed
        if ($start > 1) {
            $html .= '<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>';
            if ($start > 2) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
        }

        // Show pages around current page
        for ($i = $start; $i <= $end; $i++) {
            $active = $i === $this->currentPage ? 'active' : '';
            $html .= '<li class="page-item ' . $active . '">';
            $html .= '<a class="page-link" href="#" data-page="' . $i . '">' . $i . '</a></li>';
        }

        // Show last page and ellipsis if needed
        if ($end < $totalPages) {
            if ($end < $totalPages - 1) {
                $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
            }
            $html .= '<li class="page-item"><a class="page-link" href="#" data-page="' . $totalPages . '">' . $totalPages . '</a></li>';
        }

        // Next button
        $nextDisabled = $this->currentPage >= $totalPages ? 'disabled' : '';
        $html .= '<li class="page-item ' . $nextDisabled . '">';
        $html .= '<a class="page-link" href="#" data-page="' . min($totalPages, $this->currentPage + 1) . '" ' . ($nextDisabled ? '' : 'aria-label="Next"') . '>';
        $html .= '<span aria-hidden="true">&raquo;</span></a></li>';

        $html .= '</ul></nav>';

        return $html;
    }
}
