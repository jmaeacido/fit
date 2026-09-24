import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { ArrowUpRight, ArrowRight, Check, CheckCircle2, ShieldCheck, Shirt, X, Download, LogOut, Expand, LoaderCircle } from 'lucide-react';

const { sizes, designs } = window.shirtSettings;
async function api(url, body) {
    const response = await fetch(url, { method: body ? 'POST' : 'GET', headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } : {}) }, ...(body ? { body: JSON.stringify(body) } : {}) });
    const data = await response.json().catch(() => ({}));
    if (!response.ok) { const error = new Error(response.status === 419 ? 'Your session expired. Refresh the page and try again.' : response.status === 429 ? 'Too many attempts. Please wait a minute and try again.' : data.message || 'Something went wrong. Please try again.'); error.fields = data.errors; if (response.status === 401) location.href = '/login'; throw error; }
    return data;
}
function App() {
    const path = location.pathname;
    return <><header className="header"><a href="/" className="brand" aria-label="FIT home">fit<span>®</span></a><span className="header-caption">GOOD DESIGN. YOUR FIT.</span><a className="header-link" href={path === '/' ? '/login' : '/'}>{path === '/' ? 'Admin access' : 'Back to collection'}<ArrowUpRight size={15}/></a></header>{path === '/admin' ? <Admin/> : path === '/login' ? <Login/> : <Selection/>}<footer><a className="brand small" href="/">fit<span>®</span></a><span>A little personality. A perfect fit.</span><span>MADE TO BE YOURS.</span></footer></>;
}
const browserEntryKey = 'fit.selection.v1';
function readBrowserEntry() {
    try {
        const entry = JSON.parse(localStorage.getItem(browserEntryKey));
        if (!entry || !/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(entry.request_key)) return null;
        if (!['name', 'display_name', 'display_number', 'size'].every(field => typeof entry[field] === 'string') || !Array.isArray(entry.designs) || !entry.designs.every(id => ['1', '2'].includes(id))) return null;
        return entry;
    } catch { return null; }
}
function Selection() {
    const [remembered] = useState(readBrowserEntry);
    const [entryId, setEntryId] = useState(remembered?.id || null), [storageError, setStorageError] = useState('');
    const [name, setName] = useState(remembered?.name || ''), [displayName, setDisplayName] = useState(remembered?.display_name || ''), [displayNumber, setDisplayNumber] = useState(remembered?.display_number || ''), [size, setSize] = useState(remembered?.size || ''), [selected, setSelected] = useState(remembered?.designs || []), [busy, setBusy] = useState(false), [error, setError] = useState(''), [fields, setFields] = useState({}), [success, setSuccess] = useState(null), [preview, setPreview] = useState(null);
    const key = useRef(remembered?.request_key || crypto.randomUUID()), locked = useRef(false), dialog = useRef(null);
    function persist(id = entryId) {
        try {
            localStorage.setItem(browserEntryKey, JSON.stringify({ id, request_key: key.current, name, display_name: displayName, display_number: displayNumber, size, designs: selected }));
            setStorageError('');
            return true;
        } catch {
            setStorageError('Enable browser storage to save your entry and edit it later.');
            return false;
        }
    }
    useEffect(() => { persist(); }, [name, displayName, displayNumber, size, selected, entryId]);
    useEffect(() => { setFields(previous => ({ ...previous, name: undefined })); }, [name]);
    useEffect(() => { setFields(previous => ({ ...previous, display_name: undefined })); }, [displayName]);
    useEffect(() => { setFields(previous => ({ ...previous, display_number: undefined })); }, [displayNumber]);
    useEffect(() => { setFields(previous => ({ ...previous, size: undefined })); }, [size]);
    useEffect(() => { setFields(previous => ({ ...previous, designs: undefined })); }, [selected]);
    useEffect(() => { if (preview) dialog.current?.showModal(); }, [preview]);
    function toggle(id) { if (!locked.current) setSelected(previous => previous.includes(id) ? previous.filter(x => x !== id) : [...previous, id]); }
    async function submit(event) {
        event.preventDefault(); if (locked.current) return;
        const errors = {}; if (!name.trim()) errors.name = ['Please enter your full name.']; if (!displayName.trim()) errors.display_name = ['Please enter your preferred display name.']; if (!/^[0-9]{2}$/.test(displayNumber)) errors.display_number = ['Enter exactly two digits (00-99).']; if (!size) errors.size = ['Choose your shirt size.']; if (!selected.length) errors.designs = ['Choose at least one design.'];
        setFields(errors); setError(''); if (Object.keys(errors).length) return;
        if (!persist()) return;
        locked.current = true; setBusy(true);
        try {
            const result = await api('/submissions/save', { name: name.trim(), display_name: displayName.trim(), display_number: displayNumber, size, designs: selected, request_key: key.current });
            setEntryId(result.id); persist(result.id); setSuccess(result);
        }
        catch (e) { setError(e.message); setFields(e.fields || {}); locked.current = false; } finally { setBusy(false); }
    }
    const complete = Number(!!name.trim() && !!displayName.trim() && /^[0-9]{2}$/.test(displayNumber)) + Number(!!size) + Number(!!selected.length);
    if (success) return <main className="success"><div className="success-icon"><CheckCircle2 size={42}/></div><p className="eyebrow">YOU’RE ON THE LIST</p><h1>Great choice, {name.trim().split(' ')[0]}<span>.</span></h1><p>Your shirt selection has been saved.</p><div className="receipt"><span>Submission #{success.id}</span><strong>{name}</strong><p>Display: {displayName} / {displayNumber}</p><p>Size {size} · {selected.map(id => `Design ${id}`).join(' & ')}</p></div><p className="hint">Your entry is remembered in this browser. Return here anytime to edit it.</p>{storageError && <p className="error" role="alert">{storageError}</p>}<button type="button" className="primary" onClick={() => { locked.current = false; setSuccess(null); }}>Edit my entry <ArrowRight size={18}/></button></main>;
    return <main className="public-main"><section className="intro"><div><p className="eyebrow"><span className="dot"/> THE SHIRT COLLECTION</p><h1>Good things.<br/>Made <span>for you.</span><svg className="spark" viewBox="0 0 60 60" aria-hidden="true"><path d="M30 2v56M2 30h56M10 10l40 40M10 50l40-40"/></svg></h1><p className="intro-copy">Two designs. Your size. Your call.<br/>Pick your favorites and we’ll take it from there.</p></div><div className="intro-note"><div className="note-icon"><Shirt size={25}/></div><strong>Find your everyday favorite.</strong><span>One design or both — make it yours.</span></div></section>
    <form onSubmit={submit} noValidate><p className="browser-note">{entryId ? `Editing your saved entry #${entryId}. Changes update the same entry.` : "No login needed. Your details are remembered in this browser."} Use the same browser to edit later; clearing its site data removes access.</p>{storageError && <p className="error" role="alert">{storageError}</p>}<div className="selection-grid"><section className="details panel"><div className="section-title"><span className="step">01</span><h2>First, your details</h2></div><p className="section-copy">Let’s get the right fit for you.</p><label htmlFor="name">Full name <span className="required">*</span></label><input id="name" autoComplete="name" placeholder="e.g. Alex Morgan" maxLength={150} value={name} disabled={busy} onChange={e => setName(e.target.value)} aria-invalid={!!fields.name} aria-describedby={fields.name ? 'name-error' : undefined}/>{fields.name && <p id="name-error" className="field-error">{fields.name[0]}</p>}
    <div className="display-field"><label htmlFor="display-name">Preferred display name <span className="required">*</span></label><input id="display-name" required maxLength={150} placeholder="e.g. MORGAN" value={displayName} disabled={busy} onChange={e => setDisplayName(e.target.value)} aria-invalid={!!fields.display_name} aria-describedby={fields.display_name ? 'display-name-error' : 'display-name-hint'}/><p id="display-name-hint" className="hint">The name you want displayed on your shirt.</p>{fields.display_name && <p id="display-name-error" className="field-error">{fields.display_name[0]}</p>}</div>
    <div className="display-field"><label htmlFor="display-number">Preferred display number <span className="required">*</span></label><input id="display-number" type="text" inputMode="numeric" pattern="[0-9]{2}" required minLength={2} maxLength={2} placeholder="e.g. 07" value={displayNumber} disabled={busy} onChange={e => setDisplayNumber(e.target.value)} aria-invalid={!!fields.display_number} aria-describedby={fields.display_number ? 'display-number-error' : 'display-number-hint'}/><p id="display-number-hint" className="hint">Enter exactly two digits, from 00 to 99.</p>{fields.display_number && <p id="display-number-error" className="field-error">{fields.display_number[0]}</p>}</div>
    <fieldset disabled={busy}><legend>Shirt size <span className="required">*</span></legend><div className="size-grid">{sizes.map(s => <label key={s} className={`size ${size === s ? 'active' : ''}`}><input type="radio" name="size" value={s} checked={size === s} onChange={() => setSize(s)}/>{s}</label>)}</div><p className="hint">Choose the size you usually wear.</p>{fields.size && <p className="field-error">{fields.size[0]}</p>}</fieldset><div className="detail-note"><ShieldCheck size={19}/><p>Your details and your favorites.<br/><strong>No account needed.</strong></p></div></section>
    <section className="design-section"><div className="design-heading"><div><div className="section-title"><span className="step">02</span><h2>Pick your design</h2></div><p className="section-copy">Go with one. Or get the best of both.</p></div><span className="multi-badge">SELECT ONE OR BOTH</span></div><div className="design-grid">{designs.map(d => <DesignCard key={d.id} design={d} selected={selected.includes(d.id)} busy={busy} toggle={() => toggle(d.id)} openPreview={(view) => setPreview({ ...d, view })}/>)}</div>{fields.designs && <p className="field-error">{fields.designs[0]}</p>}<p className="design-footnote"><Shirt size={15}/> Same great fit. Two ways to wear it.</p></section></div>
    <section className="submit-panel"><div><p className="eyebrow">YOUR SELECTION</p><p className="selection-summary">{size || 'Choose your size'}<span> / </span>{selected.length ? selected.map(id => `Design ${id}`).join(' + ') : 'Pick your favorite design'}</p></div><div className="submit-actions"><span>{complete === 3 ? 'Looking good. Ready when you are.' : `${complete} of 3 steps complete`}</span><button className="primary" disabled={busy} type="submit">{busy ? 'Saving selection' : entryId ? 'Save changes' : 'Submit selection'}{busy ? <LoaderCircle className="spin" size={18}/> : <ArrowRight size={18}/>}</button></div></section>{error && <p className="error" role="alert">{error}</p>}<p className="privacy">Your details are only used to organize shirt selections.</p></form>
    {preview && <dialog ref={dialog} aria-labelledby="preview-title" onCancel={() => setPreview(null)} onClick={e => { if (e.target === dialog.current) setPreview(null); }}><button className="close" type="button" onClick={() => setPreview(null)} aria-label="Close preview"><X/></button><img className="gallery-large" src={preview.view.image} alt={preview.name + ' - ' + preview.view.label + ' view'}/><h2 id="preview-title">{preview.name} / {preview.view.label}</h2><ViewControls design={preview} current={preview.view} onChange={view => setPreview(previous => ({ ...previous, view }))}/><p>{preview.subtitle}</p></dialog>}</main>;
}

function ViewControls({ design, current, onChange }) {
    return <div className="view-controls" role="group" aria-label={design.name + ' views'}>{(design.views || [{ label: 'Front', image: design.image }]).map(view => <button key={view.label} type="button" aria-pressed={view.label === current.label} onClick={() => onChange(view)}>{view.label}</button>)}</div>;
}
function DesignCard({ design, selected, busy, toggle, openPreview }) {
    const [view, setView] = useState(design.views?.[0] || { label: 'Front', image: design.image });
    return <article className={'design-card ' + (selected ? 'selected' : '')}>
        <div className="design-image">
            <button className="gallery-image" type="button" onClick={() => openPreview(view)} aria-label={'Enlarge ' + design.name + ' ' + view.label.toLowerCase() + ' view'}><img src={view.image} alt={design.name + ' - ' + view.label + ' view'} decoding="async"/><span className="zoom"><Expand size={16}/></span></button>
            <label className="gallery-select"><input type="checkbox" checked={selected} disabled={busy} onChange={toggle} aria-label={'Select ' + design.name}/><span className="check-box">{selected && <Check size={16}/>}</span></label>
        </div>
        <ViewControls design={design} current={view} onChange={setView}/>
        <button type="button" className="design-info" onClick={toggle} disabled={busy} aria-pressed={selected}><span><strong>{design.name}</strong><small>{design.subtitle}</small></span><span className="select-text">{selected ? 'Selected' : 'Select'} {selected ? <Check size={14}/> : <span>+</span>}</span></button>
    </article>;
}

function Login() {
    const [error, setError] = useState(''), [busy, setBusy] = useState(false);
    async function submit(e) { e.preventDefault(); if (busy) return; setBusy(true); setError(''); const data = new FormData(e.currentTarget); try { await api('/login', Object.fromEntries(data)); location.href = '/admin'; } catch (err) { setError(err.message); setBusy(false); } }
    return <main className="login-wrap"><section className="panel login"><p className="eyebrow">THE ORGANIZER’S CORNER</p><h1>Welcome back<span>.</span></h1><p className="section-copy">Sign in to manage shirt selections.</p><form onSubmit={submit}><label htmlFor="login">Email or username</label><input id="login" name="login" type="text" autoComplete="username" autoCapitalize="none" spellCheck={false} required/><label htmlFor="password">Password</label><input id="password" name="password" type="password" autoComplete="current-password" required/>{error && <p className="error" role="alert">{error}</p>}<button className="primary" disabled={busy}>{busy ? 'Signing in…' : 'Sign in'}<ArrowRight size={18}/></button></form></section></main>;
}
function Admin() {
    const [size, setSize] = useState(''), [design, setDesign] = useState(''), [page, setPage] = useState(1), [data, setData] = useState(null), [error, setError] = useState(''), [loading, setLoading] = useState(true), [refresh, setRefresh] = useState(0);
    const query = new URLSearchParams({ size, design, page }).toString();
    useEffect(() => { let active = true; setLoading(true); setError(''); api(`/admin/submissions?${query}`).then(result => { if (active) setData(result); }).catch(e => { if (active) setError(e.message); }).finally(() => { if (active) setLoading(false); }); return () => { active = false; }; }, [query, refresh]);
    async function logout() { try { await api('/logout', {}); location.href = '/login'; } catch (e) { setError(e.message); } }
    return <main className="admin"><div className="admin-heading"><div><p className="eyebrow">COLLECTION OVERVIEW</p><h1>Shirt selections<span>.</span></h1></div><button className="secondary" onClick={logout}><LogOut size={16}/> Sign out</button></div><div className="filters"><label>Shirt size<select value={size} onChange={e => { setSize(e.target.value); setPage(1); }}><option value="">All sizes</option>{sizes.map(s => <option key={s}>{s}</option>)}</select></label><label>Design<select value={design} onChange={e => { setDesign(e.target.value); setPage(1); }}><option value="">All designs</option>{designs.map(d => <option key={d.id} value={d.id}>{d.name}</option>)}</select></label><a className="primary" href={`/admin/export?${query}`}><Download size={17}/> Export CSV</a></div>{error && <p className="error" role="alert">{error} <button className="secondary" onClick={() => setRefresh(n => n + 1)}>Retry</button></p>}{loading ? <p role="status">Loading selections…</p> : data && <><div className="stats"><div className="stat"><span>Submissions</span><strong>{data.total}</strong></div>{designs.map(d => <div className="stat" key={d.id}><span>{d.name} selections</span><strong>{data.designs[d.id] || 0}</strong></div>)}</div><div className="size-totals">{sizes.map(s => <div key={s}><span>{s}</span><strong>{data.sizes[s] || 0}</strong></div>)}</div><p className="hint">Totals reflect the current filters. A submission with both designs counts toward each design.</p><div className="table-wrap"><table><thead><tr><th>Full name</th><th>Display name</th><th>Display number</th><th>Size</th><th>Designs</th><th>Submitted</th></tr></thead><tbody>{data.submissions.data.map(row => <tr key={row.id}><td>{row.name}</td><td>{row.display_name ?? '?'}</td><td>{row.display_number ?? '?'}</td><td><span className="size-tag">{row.size}</span></td><td>{row.designs.map(id => `Design ${id}`).join(', ')}</td><td>{new Date(row.created_at).toLocaleString()}</td></tr>)}</tbody></table>{!data.total && <div className="empty"><Shirt/><h2>No selections yet</h2><p>{size || design ? 'Try changing the filters.' : 'Submitted selections will appear here.'}</p></div>}</div><div className="pagination"><span>Page {data.submissions.current_page} of {data.submissions.last_page} · {data.total} submissions</span><button className="secondary" disabled={page <= 1} onClick={() => setPage(p => p - 1)}>Previous</button><button className="secondary" disabled={page >= data.submissions.last_page} onClick={() => setPage(p => p + 1)}>Next</button></div></>}</main>;
}
createRoot(document.getElementById('root')).render(<App/>);
