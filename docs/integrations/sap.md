# SAP S/4HANA Integration

The initial connector consumes Product Master through OData (`API_PRODUCT_SRV`). The base URL and path are configurable to support different deployments.

## Normalization

| SAP | LoraTrack |
| --- | --- |
| `Product` | external reference and SKU code |
| `ProductDescription` | product/SKU name |
| `BaseUnit` | base unit |
| `ProductType` | attribute |
| `ProductGroup` | attribute |

Codes are stored as text and preserve leading zeroes. Each reference is unique by connector and SAP identifier. A checksum avoids unnecessary writes.

Product Master does not represent stock by plant or warehouse. That capability requires a separate API and synchronizer.

Reference: <https://api.sap.com/api/API_PRODUCT_SRV/overview>
