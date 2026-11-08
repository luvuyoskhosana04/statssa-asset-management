<?php

namespace Tests\Unit\Presenters;

use App\Presenters\UploadedFilesPresenter;
use Tests\TestCase;

class UploadedFilesPresenterTest extends TestCase
{
    public function test_layout_includes_actions_column_by_default()
    {
        $layout = json_decode(UploadedFilesPresenter::dataTableLayout(), true);

        $this->assertContains('available_actions', array_column($layout, 'field'));
    }

    public function test_layout_omits_actions_column_when_hidden()
    {
        $layout = json_decode(
            UploadedFilesPresenter::dataTableLayout(['available_actions']),
            true
        );

        $this->assertNotContains('available_actions', array_column($layout, 'field'));
    }

    public function test_file_list_leads_with_name_type_size_and_date()
    {
        $layout = collect(json_decode(UploadedFilesPresenter::dataTableLayout(), true));
        $visible = $layout->filter(fn ($column) => $column['visible'] ?? false)->pluck('field')->values()->all();

        $this->assertSame(['filename', 'filetype', 'size', 'created_at', 'image', 'download', 'available_actions'], $visible);
        $this->assertFalse($layout->firstWhere('field', 'note')['visible']);
    }
}
