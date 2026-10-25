-- =====================================================================
-- 018: الدعم الفني بيعمل الاشتراك ويسجّل الدفعة وهو بيعمل عميل جديد
-- - create_subscription: الدعم الفني مسموحله
-- - record_payment(): تسجيل دفع من الفلوس أو الدعم الفني — بيمنع دفع أكتر من الباقي على الفاتورة
-- - pay_subscription(): يسجّل الدفعة على فاتورة الاشتراك اللي لسه اتعمل (من شاشة العميل الجديد)
-- الـ insert المباشر في payments فاضل للفلوس بس (RLS من 003)
-- =====================================================================

create or replace function create_subscription(
  p_client uuid, p_name text, p_price numeric, p_items jsonb,
  p_start date default null, p_validity_days int default 30,
  p_overage_rate numeric default null, p_plan uuid default null, p_by uuid default null
) returns uuid language plpgsql security definer set search_path = public as $$
declare v_sub uuid; v_start date := coalesce(p_start, cairo_today()); v_was_churned boolean;
begin
  perform require_role(array['account_manager','sales','finance','support']);
  select status = 'churned' into v_was_churned from clients where id = p_client;

  insert into subscriptions (client_id, plan_id, name, price, validity_days, overage_rate, started_at)
  values (p_client, p_plan, p_name, p_price, p_validity_days, p_overage_rate, v_start)
  returning id into v_sub;

  insert into subscription_items (subscription_id, deliverable_type, quantity)
  select v_sub, x.type, x.qty from jsonb_to_recordset(p_items) as x(type text, qty numeric);

  perform create_cycle(v_sub, v_start);

  insert into subscription_events (subscription_id, client_id, event_type, mrr_delta, effective_date, details, created_by)
  values (v_sub, p_client, case when v_was_churned then 'reactivate' else 'new' end,
          round(p_price * 30.0 / p_validity_days, 2), v_start, p_items, p_by);

  update clients set status = 'active', churned_at = null where id = p_client and status <> 'active';
  return v_sub;
end $$;

create or replace function record_payment(p_invoice uuid, p_amount numeric, p_method text, p_reference text default null)
returns uuid language plpgsql security definer set search_path = public as $$
declare inv invoices; v_id uuid;
begin
  perform require_role(array['finance','support']);
  select * into inv from invoices where id = p_invoice for update;
  if inv.id is null then raise exception 'الفاتورة مش موجودة'; end if;
  if inv.status = 'void' then raise exception 'الفاتورة دي اتلغت'; end if;
  if coalesce(p_amount, 0) <= 0 then raise exception 'اكتب المبلغ'; end if;
  if p_amount > inv.amount - inv.paid_amount then
    raise exception 'المبلغ أكبر من الباقي على الفاتورة (%)', trim_scale(inv.amount - inv.paid_amount);
  end if;
  insert into payments (invoice_id, amount, method, reference, received_by)
  values (p_invoice, p_amount, p_method, nullif(btrim(p_reference), ''), auth.uid())
  returning id into v_id;
  return v_id;
end $$;

create or replace function pay_subscription(p_sub uuid, p_amount numeric, p_method text, p_reference text default null)
returns uuid language plpgsql security definer set search_path = public as $$
declare v_inv uuid;
begin
  select i.id into v_inv
    from invoices i join cycles c on c.id = i.cycle_id
   where c.subscription_id = p_sub and i.kind = 'subscription' and i.status in ('unpaid','partial')
   order by c.start_date limit 1;
  if v_inv is null then raise exception 'مفيش فاتورة مستنية دفع للاشتراك ده'; end if;
  return record_payment(v_inv, p_amount, p_method, p_reference);
end $$;

revoke execute on function record_payment(uuid, numeric, text, text), pay_subscription(uuid, numeric, text, text) from public, anon;
grant execute on function record_payment(uuid, numeric, text, text), pay_subscription(uuid, numeric, text, text) to authenticated;
