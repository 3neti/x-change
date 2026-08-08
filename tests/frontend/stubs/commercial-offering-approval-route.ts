function storeUrl(offering: number | string) {
  return `/x/cockpit/commercial/offerings/${offering}/approvals`;
}

export const store = Object.assign(storeUrl, { url: storeUrl });
