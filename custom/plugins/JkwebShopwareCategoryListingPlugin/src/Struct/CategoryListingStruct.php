<?php declare(strict_types=1);

namespace Jkweb\Shopware\Plugin\CategoryListing\Struct;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Framework\Struct\Struct;

class CategoryListingStruct extends Struct
{
    private CategoryCollection $categories;

    private ?string $rowElementClassName = null;

    private ?string $colElementClassName = null;

    private ?string $headingPosition = null;

    public function getCategories(): CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categoryCollection): void
    {
        $this->categories = $categoryCollection;
    }

    public function getRowElementClassName(): ?string
    {
        return $this->rowElementClassName;
    }

    public function setRowElementClassName(?string $rowElementClassName): void
    {
        $this->rowElementClassName = $rowElementClassName;
    }

    public function getColElementClassName(): ?string
    {
        return $this->colElementClassName;
    }

    public function setColElementClassName(?string $colElementClassName): void
    {
        $this->colElementClassName = $colElementClassName;
    }

    public function getHeadingPosition(): ?string
    {
        return $this->headingPosition;
    }

    public function setHeadingPosition(?string $headingPosition): void
    {
        $this->headingPosition = $headingPosition;
    }
}
