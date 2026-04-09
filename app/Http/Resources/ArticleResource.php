<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'title'      => $this->title,
            'summary'    => $this->summary,
            'content'    => $this->content,
            'source_url' => $this->source_url,
            'fetched_at' => $this->fetched_at?->toIso8601String(),
        ];
    }
}
