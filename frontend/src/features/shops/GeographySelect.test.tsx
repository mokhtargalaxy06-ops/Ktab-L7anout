import { useState } from 'react'
import { render, screen } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { expect, it, vi } from 'vitest'
import { LocaleProvider } from '../../app/LocaleProvider'
import { api } from '../../shared/api/client'
import { GeographySelect } from './GeographySelect'
import type { Locations } from './shopApi'

vi.mock('../../shared/api/client', () => ({ api: { get: vi.fn() } }))

function Form() {
  const [locations, setLocations] = useState<Locations>({ region_id: '', city_id: '', locality_id: null })
  return <LocaleProvider><GeographySelect value={locations} onChange={setLocations} /></LocaleProvider>
}

it('loads scoped choices and clears city and locality when the region changes', async () => {
  const rows: Record<string, object[]> = {
    '/api/v1/regions': [{ id: 'first', name_fr: 'Tanger-Tétouan-Al Hoceima' }, { id: 'other', name_fr: 'Rabat-Salé-Kénitra' }],
    '/api/v1/cities?region_id=first': [{ id: 'tanger', name_fr: 'Tanger' }],
    '/api/v1/localities?city_id=tanger': [{ id: 'souani', name_fr: 'Souani' }],
    '/api/v1/cities?region_id=other': [{ id: 'rabat', name_fr: 'Rabat' }],
  }
  vi.mocked(api.get).mockImplementation(async path => ({ data: { data: rows[path] ?? [] } }))

  const user = userEvent.setup()
  render(<Form />)
  const region = screen.getByRole('combobox', { name: 'Région' })
  const city = screen.getByRole('combobox', { name: 'Ville ou commune' })
  const locality = screen.getByRole('combobox', { name: 'Quartier ou localité' })
  expect(city).toBeDisabled()
  await screen.findByRole('option', { name: 'Tanger-Tétouan-Al Hoceima' })
  await user.selectOptions(region, 'first')
  await screen.findByRole('option', { name: 'Tanger' })
  await user.selectOptions(city, 'tanger')
  await screen.findByRole('option', { name: 'Souani' })
  await user.selectOptions(locality, 'souani')
  await user.selectOptions(region, 'other')
  await screen.findByRole('option', { name: 'Rabat' })
  expect(city).toHaveValue('')
  expect(locality).toBeDisabled()
  expect(locality).toHaveValue('')
  expect(screen.queryByRole('option', { name: 'Tanger' })).not.toBeInTheDocument()
})
