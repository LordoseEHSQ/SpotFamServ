import { api } from '../client';

export type OtaChannel = 'stable' | 'beta' | 'dev';

export interface SystemConfiguration {
  source: 'db' | 'env';
  wifi_ssid: string | null;
  has_wifi_password: boolean;
  backend_base_url: string | null;
  ota_channel: OtaChannel;
  ota_channels: OtaChannel[];
  frontend_url: string | null;
  reader_network_complete: boolean;
  updated_at: string;
}

export interface SaveSystemConfigurationRequest {
  wifi_ssid?: string | null;
  wifi_password?: string;
  backend_base_url?: string | null;
  ota_channel?: OtaChannel;
  frontend_url?: string | null;
}

export interface SaveSystemConfigurationResponse {
  reader_network_complete: boolean;
  updated_at: string;
}

export const systemConfigApi = {
  get: () => api.get<SystemConfiguration>('/system/configuration'),
  save: (data: SaveSystemConfigurationRequest) =>
    api.put<SaveSystemConfigurationResponse>('/system/configuration', data),
};
