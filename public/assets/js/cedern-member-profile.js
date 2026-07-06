(() => {
  const form = document.querySelector('form.nc-member-profile-form')
    || document.querySelector('form[action$="/membro/perfil/completar"]');
  if (!form) {
    return;
  }

  const birthStateSelect = form.querySelector('[data-member-birth-state]');
  const birthCitySelect = form.querySelector('[data-member-birth-city]');
  const photoInput = form.querySelector('#profile_photo');
  const phoneMobileInput = form.querySelector('#phone_mobile');
  const phoneLandlineInput = form.querySelector('#phone_landline');
  const cpfInput = form.querySelector('#cpf');
  const postalCodeInput = form.querySelector('#postal_code');
  const streetAddressInput = form.querySelector('#street_address');
  const neighborhoodInput = form.querySelector('#neighborhood');
  const addressStateSelect = form.querySelector('[data-member-address-state]');
  const addressCitySelect = form.querySelector('[data-member-address-city]');

  const cityCache = new Map();
  const requestTimeoutMs = 5000;
  const maxRetries = 2;
  const postalCodeLookupMinLength = 8;

  const localCityFallbackByUf = {
    AC: ['Rio Branco'],
    AL: ['Maceió'],
    AP: ['Macapá'],
    AM: ['Manaus'],
    BA: ['Salvador'],
    CE: ['Fortaleza'],
    DF: ['Brasília'],
    ES: ['Vitória'],
    GO: ['Goiânia'],
    MA: ['São Luís'],
    MT: ['Cuiabá'],
    MS: ['Campo Grande'],
    MG: ['Belo Horizonte'],
    PA: ['Belém'],
    PB: ['João Pessoa'],
    PR: ['Curitiba'],
    PE: ['Recife'],
    PI: ['Teresina'],
    RJ: ['Rio de Janeiro'],
    RN: ['Natal'],
    RS: ['Porto Alegre'],
    RO: ['Porto Velho'],
    RR: ['Boa Vista'],
    SC: ['Florianópolis'],
    SP: ['São Paulo'],
    SE: ['Aracaju'],
    TO: ['Palmas'],
  };

  const cityStatusEls = new WeakMap();
  let postalCodeStatusEl = null;
  let postalCodeLookupController = null;
  let lastPostalCodeLookup = '';

  const sanitizeDigits = (value) => (value || '').replace(/\D+/g, '');

  const formatMobilePhone = (value) => {
    const digits = sanitizeDigits(value).slice(0, 11);

    if (digits.length <= 2) {
      return digits;
    }

    if (digits.length <= 6) {
      return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    if (digits.length <= 10) {
      return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7, 11)}`;
  };

  const formatLandlinePhone = (value) => {
    const digits = sanitizeDigits(value).slice(0, 10);

    if (digits.length <= 2) {
      return digits;
    }

    if (digits.length <= 6) {
      return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
    }

    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6, 10)}`;
  };

  const formatCpf = (value) => {
    const digits = sanitizeDigits(value).slice(0, 11);

    if (digits.length <= 3) {
      return digits;
    }

    if (digits.length <= 6) {
      return `${digits.slice(0, 3)}.${digits.slice(3)}`;
    }

    if (digits.length <= 9) {
      return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6)}`;
    }

    return `${digits.slice(0, 3)}.${digits.slice(3, 6)}.${digits.slice(6, 9)}-${digits.slice(9, 11)}`;
  };

  const formatPostalCode = (value) => {
    const digits = sanitizeDigits(value).slice(0, 8);

    if (digits.length <= 5) {
      return digits;
    }

    return `${digits.slice(0, 5)}-${digits.slice(5, 8)}`;
  };

  const applyPhoneMask = (input, formatter) => {
    if (!input) {
      return;
    }

    const onInput = () => {
      input.value = formatter(input.value);
    };

    input.addEventListener('input', onInput);
    onInput();
  };

  const clearCities = (cityField, placeholder = 'Selecione a cidade') => {
    if (!cityField) {
      return;
    }

    cityField.innerHTML = '';
    const defaultOption = document.createElement('option');
    defaultOption.value = '';
    defaultOption.textContent = placeholder;
    cityField.appendChild(defaultOption);
  };

  const ensureCityStatus = (cityField) => {
    if (!cityField) {
      return null;
    }

    const existingStatus = cityStatusEls.get(cityField);
    if (existingStatus instanceof HTMLElement) {
      return existingStatus;
    }

    const cityStatusEl = document.createElement('small');
    cityStatusEl.className = 'nc-member-profile-help';
    cityStatusEl.setAttribute('data-member-city-status', 'true');

    const parent = cityField.parentElement;
    if (parent) {
      parent.appendChild(cityStatusEl);
    }

    cityStatusEls.set(cityField, cityStatusEl);
    return cityStatusEl;
  };

  const setCityStatus = (cityField, message = '') => {
    const status = ensureCityStatus(cityField);
    if (!status) {
      return;
    }

    status.textContent = message;
  };

  const ensurePostalCodeStatus = () => {
    if (!postalCodeInput) {
      return null;
    }

    if (postalCodeStatusEl instanceof HTMLElement) {
      return postalCodeStatusEl;
    }

    postalCodeStatusEl = document.createElement('small');
    postalCodeStatusEl.className = 'nc-member-profile-help';
    postalCodeStatusEl.setAttribute('data-member-postal-code-status', 'true');

    const parent = postalCodeInput.parentElement;
    if (parent) {
      parent.appendChild(postalCodeStatusEl);
    }

    return postalCodeStatusEl;
  };

  const setPostalCodeStatus = (message = '') => {
    const status = ensurePostalCodeStatus();
    if (!status) {
      return;
    }

    status.textContent = message;
  };

  const populateCities = (cityField, cities, selectedCity = '') => {
    if (!cityField) {
      return;
    }

    clearCities(cityField);
    const normalizedSelectedCity = selectedCity.trim().toLowerCase();
    let hasSelectedCity = false;

    cities.forEach((city) => {
      const option = document.createElement('option');
      option.value = city;
      option.textContent = city;
      if (normalizedSelectedCity && city.toLowerCase() === normalizedSelectedCity) {
        option.selected = true;
        hasSelectedCity = true;
      }
      cityField.appendChild(option);
    });

    if (selectedCity && !hasSelectedCity) {
      const fallbackOption = document.createElement('option');
      fallbackOption.value = selectedCity;
      fallbackOption.textContent = selectedCity;
      fallbackOption.selected = true;
      cityField.appendChild(fallbackOption);
    }

    cityField.disabled = false;
  };

  const fetchWithTimeout = async (url, timeoutMs) => {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => {
      controller.abort();
    }, timeoutMs);

    try {
      return await fetch(url, {
        method: 'GET',
        signal: controller.signal,
      });
    } finally {
      window.clearTimeout(timeoutId);
    }
  };

  const fetchJsonWithTimeout = async (url, timeoutMs, signal = null) => {
    const controller = new AbortController();
    const timeoutId = window.setTimeout(() => {
      controller.abort();
    }, timeoutMs);

    const abortHandler = () => {
      controller.abort();
    };

    if (signal) {
      if (signal.aborted) {
        controller.abort();
      } else {
        signal.addEventListener('abort', abortHandler, { once: true });
      }
    }

    try {
      const response = await fetch(url, {
        method: 'GET',
        signal: controller.signal,
      });

      if (!response.ok) {
        throw new Error('Resposta inválida da API.');
      }

      return await response.json();
    } finally {
      window.clearTimeout(timeoutId);
      if (signal) {
        signal.removeEventListener('abort', abortHandler);
      }
    }
  };

  const fetchCitiesByState = async (uf) => {
    if (!uf) {
      return [];
    }

    const normalizedUf = uf.toUpperCase();
    if (cityCache.has(normalizedUf)) {
      return cityCache.get(normalizedUf);
    }

    const endpoint = `https://servicodados.ibge.gov.br/api/v1/localidades/estados/${normalizedUf}/municipios`;

    let lastError = null;
    for (let attempt = 1; attempt <= maxRetries; attempt += 1) {
      try {
        const response = await fetchWithTimeout(endpoint, requestTimeoutMs);

        if (!response.ok) {
          throw new Error('Resposta inválida da API de cidades.');
        }

        const data = await response.json();
        const cities = Array.isArray(data)
          ? data
              .map((item) => (item && typeof item.nome === 'string' ? item.nome : ''))
              .filter((name) => name !== '')
          : [];

        if (!cities.length) {
          throw new Error('Lista de cidades vazia.');
        }

        cityCache.set(normalizedUf, cities);
        return cities;
      } catch (error) {
        lastError = error;
      }
    }

    throw lastError || new Error('Não foi possível carregar cidades.');
  };

  const loadCities = async (stateField, cityField, selectedCity = '') => {
    if (!stateField || !cityField) {
      return;
    }

    const uf = (stateField.value || '').trim();
    if (!uf) {
      cityField.disabled = true;
      clearCities(cityField);
      setCityStatus(cityField, '');
      return;
    }

    cityField.disabled = true;
    clearCities(cityField, 'Carregando cidades...');
    setCityStatus(cityField, '');

    try {
      const cities = await fetchCitiesByState(uf);
      populateCities(cityField, cities, selectedCity);
      setCityStatus(cityField, '');
    } catch (error) {
      const fallbackCities = localCityFallbackByUf[(uf || '').toUpperCase()] || [];

      if (fallbackCities.length > 0) {
        populateCities(cityField, fallbackCities, selectedCity);
        setCityStatus(cityField, 'API indisponível no momento. Exibindo lista local temporária.');
      } else {
        cityField.disabled = true;
        clearCities(cityField, 'Não foi possível carregar as cidades');
        setCityStatus(cityField, 'Falha ao consultar a API de cidades. Tente novamente.');
      }
    }
  };

  const initCityCascade = (stateField, cityField) => {
    if (!stateField || !cityField) {
      return;
    }

    const selectedCityFromServer = cityField.getAttribute('data-selected-city') || cityField.value || '';
    const initialUf = (stateField.value || '').trim();

    cityField.disabled = true;
    clearCities(cityField);

    if (initialUf) {
      loadCities(stateField, cityField, selectedCityFromServer);
    }

    stateField.addEventListener('change', () => {
      cityField.setAttribute('data-selected-city', '');
      loadCities(stateField, cityField, '');
    });
  };

  const initPhotoPreview = () => {
    if (!photoInput) {
      return;
    }

    const previewWrap = form.querySelector('.nc-member-photo-preview-wrap');
    if (!previewWrap) {
      return;
    }

    const setPreview = (url) => {
      previewWrap.innerHTML = '';
      const image = document.createElement('img');
      image.className = 'nc-member-photo-preview';
      image.src = url;
      image.alt = 'Pré-visualização da foto de perfil';
      previewWrap.appendChild(image);
    };

    photoInput.addEventListener('change', () => {
      const file = photoInput.files && photoInput.files[0] ? photoInput.files[0] : null;
      if (!file) {
        return;
      }

      if (!file.type.startsWith('image/')) {
        return;
      }

      const objectUrl = URL.createObjectURL(file);
      setPreview(objectUrl);
    });
  };

  const fillAddressFromPostalCode = async (address) => {
    if (streetAddressInput && address.street) {
      streetAddressInput.value = address.street;
    }

    if (neighborhoodInput && address.neighborhood) {
      neighborhoodInput.value = address.neighborhood;
    }

    if (addressStateSelect && address.state) {
      const normalizedState = address.state.toUpperCase();
      const optionExists = Array.from(addressStateSelect.options || []).some(
        (option) => option.value === normalizedState,
      );

      if (optionExists) {
        addressStateSelect.value = normalizedState;
      }
    }

    if (addressCitySelect && address.city) {
      addressCitySelect.setAttribute('data-selected-city', address.city);

      if (addressStateSelect && addressStateSelect.value) {
        await loadCities(addressStateSelect, addressCitySelect, address.city);
      } else {
        populateCities(addressCitySelect, [address.city], address.city);
      }
    }
  };

  const fetchPostalCodeAddress = async (postalCodeDigits, signal) => {
    const providers = [
      {
        url: `https://viacep.com.br/ws/${postalCodeDigits}/json/`,
        map: (data) => {
          if (!data || data.erro) {
            return null;
          }

          return {
            street: typeof data.logradouro === 'string' ? data.logradouro.trim() : '',
            neighborhood: typeof data.bairro === 'string' ? data.bairro.trim() : '',
            city: typeof data.localidade === 'string' ? data.localidade.trim() : '',
            state: typeof data.uf === 'string' ? data.uf.trim() : '',
          };
        },
      },
      {
        url: `https://brasilapi.com.br/api/cep/v2/${postalCodeDigits}`,
        map: (data) => {
          if (!data || data.errors) {
            return null;
          }

          return {
            street: typeof data.street === 'string' ? data.street.trim() : '',
            neighborhood: typeof data.neighborhood === 'string' ? data.neighborhood.trim() : '',
            city: typeof data.city === 'string' ? data.city.trim() : '',
            state: typeof data.state === 'string' ? data.state.trim() : '',
          };
        },
      },
    ];

    let lastError = null;

    for (const provider of providers) {
      try {
        const data = await fetchJsonWithTimeout(provider.url, requestTimeoutMs, signal);
        const address = provider.map(data);

        if (address) {
          return address;
        }
      } catch (error) {
        lastError = error;

        if (signal && signal.aborted) {
          throw error;
        }
      }
    }

    if (lastError) {
      throw lastError;
    }

    return null;
  };

  const lookupPostalCode = async (force = false) => {
    if (!postalCodeInput) {
      return;
    }

    const postalCodeDigits = sanitizeDigits(postalCodeInput.value);
    if (postalCodeDigits.length !== postalCodeLookupMinLength) {
      if (force && postalCodeDigits.length > 0) {
        setPostalCodeStatus('Informe um CEP completo com 8 dígitos.');
      } else if (postalCodeDigits.length === 0) {
        setPostalCodeStatus('');
      }
      return;
    }

    const addressAlreadyFilled = Boolean(
      (streetAddressInput && streetAddressInput.value.trim())
      || (neighborhoodInput && neighborhoodInput.value.trim())
      || (addressCitySelect && addressCitySelect.value.trim())
      || (addressStateSelect && addressStateSelect.value.trim()),
    );

    if (!force && postalCodeDigits === lastPostalCodeLookup && addressAlreadyFilled) {
      return;
    }

    if (postalCodeLookupController) {
      postalCodeLookupController.abort();
    }

    postalCodeLookupController = new AbortController();
    const lookupController = postalCodeLookupController;
    setPostalCodeStatus('Consultando CEP...');

    try {
      const address = await fetchPostalCodeAddress(postalCodeDigits, lookupController.signal);

      if (!address) {
        setPostalCodeStatus('CEP não encontrado. Preencha o endereço manualmente.');
        return;
      }

      await fillAddressFromPostalCode(address);
      lastPostalCodeLookup = postalCodeDigits;
      setPostalCodeStatus('Endereço preenchido automaticamente a partir do CEP.');
    } catch (error) {
      if (lookupController.signal.aborted) {
        return;
      }

      setPostalCodeStatus('Não foi possível consultar o CEP agora. Você pode preencher manualmente.');
    } finally {
      if (postalCodeLookupController === lookupController) {
        postalCodeLookupController = null;
      }
    }
  };

  const initPostalCodeLookup = () => {
    if (!postalCodeInput) {
      return;
    }

    postalCodeInput.addEventListener('blur', () => {
      lookupPostalCode(true);
    });

    postalCodeInput.addEventListener('change', () => {
      lookupPostalCode(true);
    });

    postalCodeInput.addEventListener('input', () => {
      if (sanitizeDigits(postalCodeInput.value).length < postalCodeLookupMinLength) {
        lastPostalCodeLookup = '';
        setPostalCodeStatus('');
      }
    });

    const postalCodeDigits = sanitizeDigits(postalCodeInput.value);
    const addressMissing = !(
      (streetAddressInput && streetAddressInput.value.trim())
      && (neighborhoodInput && neighborhoodInput.value.trim())
      && (addressCitySelect && addressCitySelect.value.trim())
      && (addressStateSelect && addressStateSelect.value.trim())
    );

    if (postalCodeDigits.length === postalCodeLookupMinLength && addressMissing) {
      lookupPostalCode(false);
    }
  };

  applyPhoneMask(phoneMobileInput, formatMobilePhone);
  applyPhoneMask(phoneLandlineInput, formatLandlinePhone);
  applyPhoneMask(cpfInput, formatCpf);
  applyPhoneMask(postalCodeInput, formatPostalCode);
  initCityCascade(birthStateSelect, birthCitySelect);
  initCityCascade(addressStateSelect, addressCitySelect);
  initPhotoPreview();
  initPostalCodeLookup();
})();
