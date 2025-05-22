<tab id="coarNotifyReviewOffer">
    <span>{translate key="plugins.generic.coarNotifyReviewOffer.displayName"}</span>
    {url|assign:coarNotifyReviewOfferUrl router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="coarNotifyReviewOfferPlugin" category="generic" verb="settings" escape=false}
    {load_url_in_div id="coarNotifyReviewOfferSettings" url=$coarNotifyReviewOfferUrl}
</tab>