import {
  Category,
  Channel,
  Movie,
  MovieDetailInfo,
  Series,
  SeriesDetailInfo,
  EPGProgramme,
  ProviderCredentials,
} from '../types';

const ADULT_REGEX = /xxx|adult|adulto|\b18\+|\+18\b|porn|erotic|erotica|playboy|venus|hentai|red\s*light|sensual|sexy/i;

export function isAdultContent(name?: string | null): boolean {
  if (!name) return false;
  return ADULT_REGEX.test(name);
}

export class XtreamApiClient {
  private host: string;
  private user: string;
  private pass: string;

  constructor(creds: ProviderCredentials) {
    let cleanHost = (creds.host || '').trim();
    if (!cleanHost.startsWith('http://') && !cleanHost.startsWith('https://')) {
      cleanHost = `http://${cleanHost}`;
    }
    if (cleanHost.endsWith('/')) {
      cleanHost = cleanHost.slice(0, -1);
    }

    this.host = cleanHost;
    this.user = (creds.username || '').trim();
    this.pass = (creds.password || '').trim();
  }

  private getBaseUrl(): string {
    return `${this.host}/player_api.php?username=${encodeURIComponent(this.user)}&password=${encodeURIComponent(this.pass)}`;
  }

  // Helper centralizado con fallback automático (Directo -> HTTPS Fallback -> Backend Proxy)
  private async fetchData(actionParams: string): Promise<any> {
    const directUrl = actionParams ? `${this.getBaseUrl()}&${actionParams}` : this.getBaseUrl();
    const isHttpsOrigin = typeof window !== 'undefined' && window.location.protocol === 'https:';
    const isHttpHost = this.host.startsWith('http://');

    // 1. Intento Directo (solo si no es bloqueo seguro por Mixed Content navegador HTTP sobre HTTPS)
    if (!isHttpsOrigin || !isHttpHost) {
      try {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 4000);
        const res = await fetch(directUrl, { signal: controller.signal });
        clearTimeout(timeoutId);
        if (res.ok) {
          return await res.json();
        }
      } catch (err) {
        console.warn('[XtreamApi] Fetch directo no disponible:', err);
      }
    }

    // 2. Si estamos en HTTPS y el host usa HTTP, probar si el servidor tiene HTTPS disponible (ej: puerto 443 estándar)
    if (isHttpsOrigin && isHttpHost) {
      try {
        const httpsHost = this.host.replace('http://', 'https://').replace(/:\d+$/, '');
        const fallbackUrl = actionParams
          ? `${httpsHost}/player_api.php?username=${encodeURIComponent(this.user)}&password=${encodeURIComponent(this.pass)}&${actionParams}`
          : `${httpsHost}/player_api.php?username=${encodeURIComponent(this.user)}&password=${encodeURIComponent(this.pass)}`;
        const controller2 = new AbortController();
        const timeoutId2 = setTimeout(() => controller2.abort(), 2000);
        const res2 = await fetch(fallbackUrl, { signal: controller2.signal });
        clearTimeout(timeoutId2);
        if (res2.ok) {
          this.host = httpsHost; // Si el servidor soporta HTTPS estándar, adoptarlo
          return await res2.json();
        }
      } catch {
        // Fallback HTTPS no disponible en este servidor
      }
    }

    // 3. Fallback a Backend Proxy (resuelve Mixed Content y CORS para cualquier proveedor)
    try {
      let token: string | undefined;
      let isServiceMode = false;
      if (typeof localStorage !== 'undefined') {
        const saved = localStorage.getItem('gorotv_client_session');
        if (saved) {
          try {
            const session = JSON.parse(saved);
            token = session?.token;
            isServiceMode = session?.mode === 'service';
          } catch {}
        }
      }

      const headers: Record<string, string> = {};
      if (token) {
        headers['Authorization'] = `Bearer ${token}`;
      }

      let proxyUrl = actionParams ? `/api/client/xtream?${actionParams}` : '/api/client/xtream';
      // Para cuentas directas Xtream (o antes del login en modo directo), enviar credenciales en query
      if ((!isServiceMode || !token) && this.host && this.user && this.pass) {
        const sep = proxyUrl.includes('?') ? '&' : '?';
        proxyUrl += `${sep}directHost=${encodeURIComponent(this.host)}&directUser=${encodeURIComponent(this.user)}&directPass=${encodeURIComponent(this.pass)}`;
      }

      const controller3 = new AbortController();
      const timeoutId3 = setTimeout(() => controller3.abort(), 12000);
      const proxyRes = await fetch(proxyUrl, { headers, signal: controller3.signal });
      clearTimeout(timeoutId3);
      if (proxyRes.ok) {
        return await proxyRes.json();
      }
    } catch (err3) {
      console.warn('[XtreamApi] Fallback Backend Proxy falló:', err3);
    }

    return null;
  }

  async authenticate(): Promise<any> {
    const data = await this.fetchData('');
    if (!data || !data.user_info || data.user_info.auth !== 1) {
      throw new Error('Credenciales de proveedor no válidas o cuenta inactiva');
    }
    return data;
  }

  // --- CATEGORÍAS ---
  async getLiveCategories(): Promise<Category[]> {
    const data = await this.fetchData('action=get_live_categories');
    return Array.isArray(data) ? data.filter((cat) => !isAdultContent(cat.category_name)) : [];
  }

  async getVodCategories(): Promise<Category[]> {
    const data = await this.fetchData('action=get_vod_categories');
    return Array.isArray(data) ? data.filter((cat) => !isAdultContent(cat.category_name)) : [];
  }

  async getSeriesCategories(): Promise<Category[]> {
    const data = await this.fetchData('action=get_series_categories');
    return Array.isArray(data) ? data.filter((cat) => !isAdultContent(cat.category_name)) : [];
  }

  // --- STREAMS Y CONTENIDO ---
  async getLiveStreams(categoryId?: string): Promise<Channel[]> {
    const params = categoryId && categoryId !== 'all'
      ? `action=get_live_streams&category_id=${encodeURIComponent(categoryId)}`
      : 'action=get_live_streams';
    const data = await this.fetchData(params);
    return Array.isArray(data) ? data.filter((item) => !isAdultContent(item.name)) : [];
  }

  async getVodStreams(categoryId?: string): Promise<Movie[]> {
    const params = categoryId && categoryId !== 'all'
      ? `action=get_vod_streams&category_id=${encodeURIComponent(categoryId)}`
      : 'action=get_vod_streams';
    const data = await this.fetchData(params);
    return Array.isArray(data) ? data.filter((item) => !isAdultContent(item.name)) : [];
  }

  async getVodInfo(vodId: number): Promise<MovieDetailInfo> {
    const data = await this.fetchData(`action=get_vod_info&vod_id=${vodId}`);
    return data || {};
  }

  async getSeries(categoryId?: string): Promise<Series[]> {
    const params = categoryId && categoryId !== 'all'
      ? `action=get_series&category_id=${encodeURIComponent(categoryId)}`
      : 'action=get_series';
    const data = await this.fetchData(params);
    return Array.isArray(data) ? data.filter((item) => !isAdultContent(item.name)) : [];
  }

  async getSeriesInfo(seriesId: number): Promise<SeriesDetailInfo> {
    const data = await this.fetchData(`action=get_series_info&series_id=${seriesId}`);
    return data || {};
  }

  async getShortEPG(streamId: number): Promise<EPGProgramme[]> {
    try {
      const data = await this.fetchData(`action=get_short_epg&stream_id=${streamId}&limit=5`);
      return data?.epg_listings || [];
    } catch {
      return [];
    }
  }

  // --- GENERADORES DE URL DIRECTA DE VIDEO ---
  private getStreamHost(): string {
    return this.host;
  }

  getLiveStreamUrl(streamId: number, format: 'ts' | 'm3u8' = 'ts'): string {
    return `${this.getStreamHost()}/live/${this.user}/${this.pass}/${streamId}.${format}`;
  }

  getMovieStreamUrl(streamId: number, extension: string = 'mp4'): string {
    const ext = extension ? extension.replace('.', '') : 'mp4';
    return `${this.getStreamHost()}/movie/${this.user}/${this.pass}/${streamId}.${ext}`;
  }

  getSeriesStreamUrl(streamId: number, extension: string = 'mp4'): string {
    const ext = extension ? extension.replace('.', '') : 'mp4';
    return `${this.getStreamHost()}/series/${this.user}/${this.pass}/${streamId}.${ext}`;
  }
}
