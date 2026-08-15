<?php

namespace Dedoc\Scramble\DocumentTransformers;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Path;

/**
 * 按约定将 tag 拆成 "分组-模块" 两级,生成 Redoc/Scalar 支持的 x-tagGroups 扩展,
 * 使文档侧边栏呈现 分组 -> 模块 -> 接口 三级结构。
 *
 * tag 名包含 "-" 或 "/" 时拆分:前半段为分组,后半段为模块。
 * 例如 "点餐-菜品" => 分组 "点餐" / 模块 "菜品"。
 *
 * 跨分组出现同名模块时(如 "点餐-订单" 与 "服务预约-订单"),
 * 首个出现的组保留短名,后续组保留全名,以免侧边栏两组合并/歧义。
 *
 * 注意:Scalar 在存在 x-tagGroups 时会隐藏未分组的 tag,
 * 所以文档中所有 tag 都应带分组前缀,否则不会出现在侧边栏。
 *
 * 通过配置 scramble.tag_groups = false 可关闭。
 */
class TagGroupsTransformer implements DocumentTransformer
{
    public function handle(OpenApi $document, OpenApiContext $context)
    {
        $split = [];

        foreach ($document->tags as $tag) {
            if (preg_match('/^(?<group>[^\-\/]+?)\s*[-\/]\s*(?<name>.+)$/u', $tag->name, $m)) {
                $split[$tag->name] = [$m['group'], $m['name']];
            }
        }

        if ($split === []) {
            return;
        }

        $nameGroups = [];
        foreach ($split as [$group, $name]) {
            $nameGroups[$name][$group] = true;
        }
        $conflicts = array_flip(array_keys(array_filter(
            $nameGroups,
            fn ($groups) => count($groups) > 1
        )));

        $groups = [];
        $rename = [];
        $seenNames = [];

        foreach ($document->tags as $tag) {
            if (! isset($split[$tag->name])) {
                continue;
            }
            [$group, $name] = $split[$tag->name];

            $newName = (isset($conflicts[$name]) && isset($seenNames[$name])) ? $tag->name : $name;
            $seenNames[$newName] = true;
            $groups[$group][] = $newName;
            $rename[$tag->name] = $newName;
            $tag->name = $newName;
        }

        foreach ($document->paths as $path) {
            /** @var Path $path */
            foreach ($path->operations as $operation) {
                /** @var Operation $operation */
                $operation->tags = array_map(
                    fn ($tag) => $rename[$tag] ?? $tag,
                    $operation->tags
                );
            }
        }

        $document->setExtensionProperty('tagGroups', array_map(
            fn ($name, $tags) => ['name' => $name, 'tags' => $tags],
            array_keys($groups),
            $groups
        ));
    }
}
