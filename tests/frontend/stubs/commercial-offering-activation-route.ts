function storeUrl(offering: number | string) {
  return `/x/cockpit/commercial/offerings/${offering}/activations`;
}

export const store = Object.assign(storeUrl, { url: storeUrl });
