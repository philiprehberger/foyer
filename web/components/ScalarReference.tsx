"use client";

import { ApiReferenceReact } from "@scalar/api-reference-react";
import "@scalar/api-reference-react/style.css";

export function ScalarReference() {
  return (
    <ApiReferenceReact
      configuration={{
        url: "/openapi/spec.yaml",
        theme: "default",
        layout: "modern",
        darkMode: false,
        hideClientButton: false,
        hideDownloadButton: false,
        persistAuth: true,
        metaData: {
          title: "Foyer — API reference",
        },
        servers: [
          {
            url: "https://api.foyer.philiprehberger.com",
            description: "Production",
          },
        ],
      }}
    />
  );
}
