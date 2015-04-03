Create something...
{$entry = post("/entries")}
{$entry|dump}

Trash it...
{delete($entry)|dump}

Is it in the trash?
{get("/trash")|dump}

Restore it...
{put($entry, ['$restore' => true])|dump}

Does it exist?
{get($entry)|dump}
{get("/entries/:count")|dump}