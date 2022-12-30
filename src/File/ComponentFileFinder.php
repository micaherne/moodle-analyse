<?php
declare(strict_types=1);

namespace MoodleAnalyse\File;

use MoodleAnalyse\Codebase\ThirdPartyLibsReader;

class ComponentFileFinder extends FileFinder
{
    /**
     * @inheritDoc
     */
    protected function getThirdPartyLibLocations(): array
    {
        $thirdPartyLibsReader = new ThirdPartyLibsReader();
        return $thirdPartyLibsReader->getLocationsRelative($this->moodleroot);
    }

}